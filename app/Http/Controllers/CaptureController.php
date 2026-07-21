<?php

namespace App\Http\Controllers;

use App\Actions\Captures\ProcessQuickCapture;
use App\Domain\Enums\CaptureSource;
use App\Domain\Enums\CaptureStatus;
use App\Http\Requests\ConfirmCaptureRequest;
use App\Http\Requests\QuickUploadRequest;
use App\Models\Board;
use App\Models\CardCapture;
use App\Services\CardFormOptionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CaptureController extends Controller
{
    public function index()
    {
        $captures = CardCapture::pending()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('captures.index', compact('captures'));
    }

    /**
     * Canal B (in-app): recebe um ou mais arquivos selecionados/soltos na tela de Captura Rápida.
     */
    public function upload(QuickUploadRequest $request)
    {
        return $this->respondToCaptures($this->storeCaptures($request->file('arquivos'), CaptureSource::Upload));
    }

    /**
     * Canal A: recebe o POST do Web Share Target da PWA (Android, autenticado por sessão) OU do Atalho
     * da Apple (iOS, autenticado por token pessoal Sanctum — `auth:web,sanctum` na rota tenta sessão
     * primeiro, depois token). Isenta de CSRF (VerifyCsrfToken) — o POST é disparado pelo SO/Atalho, sem
     * token CSRF — mas continua exigindo uma identidade válida e a mesma validação estrita de upload. Só
     * estaciona o arquivo, nada destrutivo. Ver specs/16, Fases 2-3.
     */
    public function receive(QuickUploadRequest $request)
    {
        $token = $request->user()->currentAccessToken();
        $viaToken = $token !== null;

        if ($viaToken && ! $request->user()->tokenCan('capture:create')) {
            abort(403, 'Token sem permissão de captura.');
        }

        $captures = $this->storeCaptures(
            $request->file('arquivos'),
            $viaToken ? CaptureSource::IosShortcut : CaptureSource::PwaShare
        );

        if (! $viaToken) {
            return $this->respondToCaptures($captures);
        }

        // iOS: o Atalho não navega — devolve JSON com uma URL assinada que ele abre no Safari (sem
        // exigir login prévio; ver show()). O Atalho envia um arquivo por vez (Apêndice A da spec).
        return response()->json([
            'confirm_url' => URL::temporarySignedRoute('captures.show', now()->addMinutes(30), ['capture' => $captures[0]->id]),
        ]);
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $files
     * @return CardCapture[]
     */
    private function storeCaptures(array $files, CaptureSource $source): array
    {
        return array_map(function ($file) use ($source) {
            $path = $file->store('capturas/'.auth()->id(), 'local');

            return CardCapture::create([
                'user_id' => auth()->id(),
                'kind' => 'orcamento',
                'source' => $source,
                'status' => CaptureStatus::Pendente,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'suggested_title' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 180, ''),
            ]);
        }, $files);
    }

    /**
     * @param  CardCapture[]  $captures
     */
    private function respondToCaptures(array $captures)
    {
        if (count($captures) === 1) {
            return redirect()->route('captures.show', $captures[0]);
        }

        return redirect()->route('captures.index')
            ->with('success', count($captures).' arquivo(s) recebido(s). Confirme cada um para criar o card.');
    }

    /**
     * Tela de confirmação. Alcançável por sessão normal (Canal B / Android) OU por um link assinado sem
     * sessão prévia (Atalho iOS abrindo no Safari — ver receive()). Nesse segundo caso, o link em si só
     * poderia ter sido gerado por quem tinha o token do dono da captura, então autentica esta aba como
     * o dono, só para este fluxo pontual — as próximas requisições (ex.: criar-card) já usam sessão normal.
     */
    public function show(Request $request, CardCapture $capture, CardFormOptionsService $options)
    {
        if (! $request->user() && $request->hasValidSignature()) {
            Auth::login($capture->user);
            $request->session()->regenerate();
        }

        $this->authorize('view', $capture);

        $user = auth()->user();
        $boards = Board::active()
            ->when(
                ! $user->isAdmin() && ! $user->isCoordenador(),
                fn ($q) => $q->whereHas('users', fn ($q) => $q->whereKey($user->id))
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        $lastBoardId = session('captures.last_board_id');

        return view('captures.show', [
            'capture' => $capture,
            'boards' => $boards,
            'lastBoardId' => $lastBoardId,
            ...array_intersect_key($options->globalOptions(), array_flip(['empresas', 'fornecedores', 'events'])),
        ]);
    }

    public function store(ConfirmCaptureRequest $request, CardCapture $capture, ProcessQuickCapture $action)
    {
        $card = $action->execute($capture, $request->validated(), $request->user());

        session(['captures.last_board_id' => $card->board_id]);

        return redirect()->route('boards.show', ['board' => $card->board_id, 'abrir_card' => $card->id])
            ->with('success', 'Card criado com sucesso.');
    }

    public function destroy(CardCapture $capture)
    {
        $this->authorize('delete', $capture);

        Storage::disk('local')->delete($capture->path);
        $capture->delete();

        return redirect()->route('captures.index')->with('success', 'Captura descartada.');
    }

    /**
     * Prévia inline do arquivo em staging (miniatura de imagem / abrir PDF), só para o dono da captura.
     */
    public function preview(CardCapture $capture)
    {
        $this->authorize('view', $capture);

        abort_unless(Storage::disk('local')->exists($capture->path), 404);

        return Storage::disk('local')->response($capture->path, $capture->original_name, [
            'Content-Disposition' => 'inline; filename="'.$capture->original_name.'"',
        ]);
    }
}
