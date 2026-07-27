<?php

namespace App\Services\Bid;

use App\Models\BidAiCall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Único ponto do sistema que fala com o Google Gemini (ver specs/21 §5.1).
 *
 * Responsabilidades: montar a requisição, exigir saída por schema, medir/registrar a chamada em
 * `bid_ai_calls`, aplicar o teto diário e traduzir qualquer falha em GeminiException com mensagem
 * pronta para o usuário. Nenhum prompt mora aqui — prompts ficam nos leitores (§11).
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Teto de payload da requisição. Base64 infla ~33%, então o arquivo cru precisa caber aqui. */
    private const MAX_PAYLOAD_BYTES = 19 * 1024 * 1024;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-flash-latest');
    }

    /** Parte de texto do conteúdo. */
    public static function textPart(string $text): array
    {
        return ['text' => $text];
    }

    /** Parte binária (PDF/imagem) já em base64 — `inline_data` é o formato do generateContent. */
    public static function filePart(string $binary, string $mimeType): array
    {
        return ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($binary)]];
    }

    /**
     * Chama o modelo exigindo JSON no formato do schema.
     *
     * @param  array  $parts  partes do conteúdo (texto e/ou inline_data)
     * @param  array  $schema  responseSchema (subconjunto de JSON Schema aceito pelo Gemini)
     * @param  string  $type  `documento` | `edital` — só para o log de custo
     * @return array{data: array, raw: string, usage: array}
     *
     * @throws GeminiException
     */
    public function generate(array $parts, array $schema, string $type, ?Model $related = null, ?string $promptVersion = null): array
    {
        if (! $this->isConfigured()) {
            throw new GeminiException(
                'GEMINI_API_KEY ausente.',
                'Integração de IA indisponível — verifique a configuração do sistema.'
            );
        }

        $this->assertWithinDailyLimit();

        $payload = [
            'contents' => [['parts' => array_values($parts)]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
                'temperature' => 0,
                // Extração não precisa de raciocínio aberto: `low` zera os tokens de pensamento
                // (verificado na API — ver specs/21 §5.1) e deixa a resposta mais rápida e barata.
                'thinkingConfig' => ['thinkingLevel' => 'low'],
            ],
        ];

        $this->assertPayloadFits($payload);

        $startedAt = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = Http::withHeaders([
                'X-goog-api-key' => (string) config('services.gemini.key'),
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) config('services.gemini.timeout', 180))
                // Sem worker, cada requisição é a única chance: 503/429 do Google (picos de demanda)
                // e quedas de conexão merecem uma nova tentativa antes de devolver erro ao usuário.
                // O callback recebe (RequestException|ConnectionException, PendingRequest) — o
                // status vem da exceção, não de um segundo parâmetro de resposta.
                ->retry(
                    times: max(1, (int) config('services.gemini.attempts', 2)),
                    sleepMilliseconds: 2000,
                    when: fn (Throwable $exception) => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && in_array($exception->response->status(), [429, 500, 502, 503, 504], true)),
                    throw: false,
                )
                ->post(sprintf(self::ENDPOINT, $this->model()), $payload);
        } catch (ConnectionException $e) {
            $error = new GeminiException(
                'Falha de conexão/timeout com o Gemini: '.$e->getMessage(),
                'Não foi possível concluir a leitura no tempo esperado. Tente novamente.'
            );
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($error === null && $response !== null && $response->failed()) {
            $error = $this->mapHttpError($response->status(), $response->body());
        }

        if ($error !== null) {
            $this->logCall($type, $related, $promptVersion, $latencyMs, false, $error->httpStatus(), $error->getMessage());
            Log::warning('[licitacoes] chamada ao Gemini falhou', [
                'type' => $type, 'status' => $error->httpStatus(), 'message' => $error->getMessage(),
            ]);

            throw $error;
        }

        $body = $response->json() ?? [];
        $raw = $this->extractText($body);
        $usage = [
            'prompt_tokens' => data_get($body, 'usageMetadata.promptTokenCount'),
            'output_tokens' => data_get($body, 'usageMetadata.candidatesTokenCount'),
            'total_tokens' => data_get($body, 'usageMetadata.totalTokenCount'),
        ];

        $data = $this->decode($raw);

        if ($data === null) {
            $this->logCall($type, $related, $promptVersion, $latencyMs, false, $response->status(), 'JSON inválido na resposta', $usage);

            throw new GeminiException(
                'Resposta do Gemini não é JSON válido.',
                'A IA respondeu em um formato inesperado. Tente novamente.',
                $response->status(),
                $raw
            );
        }

        $this->logCall($type, $related, $promptVersion, $latencyMs, true, $response->status(), null, $usage);

        return ['data' => $data, 'raw' => $raw, 'usage' => $usage];
    }

    // Internos --------------------------------------------------------------

    private function mapHttpError(int $status, string $body): GeminiException
    {
        $user = match (true) {
            in_array($status, [400, 401, 403], true) => 'Integração de IA indisponível — verifique a configuração do sistema.',
            $status === 429 => 'Limite de uso da IA atingido. Tente novamente em alguns minutos.',
            default => 'A IA está indisponível neste momento. Tente novamente.',
        };

        return new GeminiException(
            "Gemini respondeu HTTP {$status}: ".mb_substr($body, 0, 300),
            $user,
            $status,
            $body
        );
    }

    /** Concatena as `parts` textuais do primeiro candidato (o resto do payload é ignorado). */
    private function extractText(array $body): string
    {
        $parts = data_get($body, 'candidates.0.content.parts', []);

        return trim(implode('', array_map(fn ($part) => (string) ($part['text'] ?? ''), $parts)));
    }

    /**
     * Com `responseMimeType: application/json` a saída já vem sem cercas, mas removê-las é barato
     * e evita quebrar caso o modelo volte a envolvê-las em ```json.
     */
    private function decode(string $raw): ?array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/mi', '', $raw));
        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function assertPayloadFits(array $payload): void
    {
        $size = strlen(json_encode($payload) ?: '');

        if ($size > self::MAX_PAYLOAD_BYTES) {
            throw new GeminiException(
                "Payload de {$size} bytes acima do limite da API.",
                'O arquivo é grande demais para a leitura automática. Envie um PDF menor ou cole o texto.'
            );
        }
    }

    /**
     * Teto diário por usuário, complementar ao throttle de rota (ver specs/21 §12). Conta também
     * as tentativas que falham — o objetivo é proteger o orçamento, não premiar erro.
     */
    private function assertWithinDailyLimit(): void
    {
        $limit = (int) config('licitacoes.ai_daily_limit', 50);

        if ($limit <= 0) {
            return;
        }

        $key = 'licitacoes:ai:'.(auth()->id() ?? 'sistema').':'.now()->toDateString();
        $used = (int) Cache::get($key, 0);

        if ($used >= $limit) {
            throw new GeminiException(
                "Teto diário de {$limit} chamadas atingido.",
                "Você atingiu o limite de {$limit} leituras de IA hoje. Tente novamente amanhã."
            );
        }

        Cache::put($key, $used + 1, now()->endOfDay());
    }

    private function logCall(
        string $type,
        ?Model $related,
        ?string $promptVersion,
        int $latencyMs,
        bool $success,
        ?int $httpStatus,
        ?string $errorMessage = null,
        array $usage = [],
    ): void {
        BidAiCall::create([
            'type' => $type,
            'related_type' => $related ? class_basename($related) : null,
            'related_id' => $related?->getKey(),
            'model' => $this->model(),
            'prompt_version' => $promptVersion,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'latency_ms' => $latencyMs,
            'success' => $success,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 255) : null,
            'user_id' => auth()->id(),
        ]);
    }
}
