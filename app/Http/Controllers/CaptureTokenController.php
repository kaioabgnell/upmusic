<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CaptureTokenController extends Controller
{
    private const TOKEN_NAME = 'captura-ios';

    /**
     * Tela "Configurar iPhone" — gerar/revogar o token pessoal e instruções do Atalho (specs/16, Fase 3).
     */
    public function edit(Request $request)
    {
        return view('captures.ios-setup', [
            'hasToken' => $request->user()->tokens()->where('name', self::TOKEN_NAME)->exists(),
            'receiveUrl' => route('captures.receive'),
        ]);
    }

    /**
     * Gera um novo token (revoga o anterior — só um por usuário). O texto puro só existe nesta resposta;
     * o banco guarda apenas o hash (padrão Sanctum).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();
        $token = $user->createToken(self::TOKEN_NAME, ['capture:create']);

        return back()->with('captureToken', $token->plainTextToken);
    }

    public function destroy(Request $request)
    {
        $request->user()->tokens()->where('name', self::TOKEN_NAME)->delete();

        return back()->with('success', 'Token revogado. O Atalho no iPhone deixará de funcionar até gerar um novo.');
    }
}
