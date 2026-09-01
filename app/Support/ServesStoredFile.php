<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resposta segura para arquivos guardados no disco `local`.
 *
 * Extraído de `CardController::downloadAttachment()` para ser reusado pelo Financeiro (specs/23):
 * o Content-Type sai do CONTEÚDO do arquivo (finfo), nunca de uma coluna `mime` que quem enviou
 * controla — servir inline com tipo forjado (um PNG declarado como text/html) seria XSS armazenado
 * na origem do sistema. O `nosniff` completa, impedindo o navegador de adivinhar outro tipo.
 */
trait ServesStoredFile
{
    /**
     * Tipos que o arquivo pode abrir direto no navegador. Allowlist fechada de propósito: qualquer
     * coisa fora daqui (inclusive HTML e SVG, que executam script) continua sendo baixada.
     *
     * Método em vez de const porque constantes em trait só existem a partir do PHP 8.2 e o
     * projeto roda em 8.1.
     *
     * @return array<string>
     */
    private static function inlineMimes(): array
    {
        return ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    protected function serveStoredFile(string $path, string $downloadName): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $disposition = in_array($mime, self::inlineMimes(), true) ? 'inline' : 'attachment';

        return $disk->response($path, $downloadName, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
