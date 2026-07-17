<?php

namespace App\Http\Controllers;

use App\Domain\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Board;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('setor')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('users.create', $this->formData());
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $this->syncBoards($user, $request);

        return redirect()->route('users.index')
            ->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('users.edit', array_merge($this->formData(), [
            'user' => $user->load('boards'),
        ]));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        $this->syncBoards($user, $request);

        return redirect()->route('users.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir o próprio usuário.');
        }

        if ($user->isAdmin() && User::where('role', UserRole::Admin->value)->count() <= 1) {
            return back()->with('error', 'Não é possível excluir o último administrador.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }

    private function formData(): array
    {
        return [
            'roles' => UserRole::options(),
            'setores' => Setor::active()->orderBy('nome')->get(),
            'boards' => Board::active()->orderBy('name')->get(),
        ];
    }

    private function syncBoards(User $user, Request $request): void
    {
        // Vínculo de quadros só faz sentido para o perfil Usuário.
        if ($user->role === UserRole::Usuario) {
            $user->boards()->sync($request->input('boards', []));
        } else {
            $user->boards()->detach();
        }
    }
}
