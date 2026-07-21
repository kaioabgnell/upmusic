<?php

namespace App\Http\Controllers;

use App\Domain\Enums\UserRole;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Atualiza a foto de perfil do usuário.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $data['avatar']->store('avatars', 'public')]);

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Serve a foto de perfil lendo direto do disco (disco `public`). Evita depender do symlink
     * `public/storage` (php artisan storage:link), que não existe em produção sem SSH e que, nesta
     * hospedagem, apontaria para o lugar errado por causa do docroot separado do código. As `<img>`
     * são requisitadas pelo navegador com o cookie de sessão (mesma origem), então `auth` funciona.
     */
    public function showAvatar(User $user)
    {
        abort_if(! $user->avatar_path, 404);
        abort_unless(Storage::disk('public')->exists($user->avatar_path), 404);

        return Storage::disk('public')->response($user->avatar_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Remove a foto de perfil do usuário (volta a exibir as iniciais).
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->role === UserRole::Admin && User::where('role', UserRole::Admin->value)->count() <= 1) {
            return Redirect::route('profile.edit')->with('error', 'Não é possível excluir sua conta: você é o único administrador.');
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
