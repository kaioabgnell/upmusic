<?php

namespace App\Http\Controllers;

use App\Actions\Users\SyncUserAccess;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AccessProfile;
use App\Models\Event;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Usuários — Administração, só Admin (specs/28 §2). */
class UserController extends Controller
{
    /** Campos do próprio usuário; o acesso (admin, perfil, eventos) vai pela SyncUserAccess. */
    private const PROFILE_FIELDS = ['name', 'email', 'password', 'setor_id', 'phone', 'active'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // Filtro "Perfil de acesso": `admin` = Administradores; um id = usuários daquele perfil.
        $perfil = (string) $request->input('perfil', '');

        $users = User::query()
            ->with(['setor', 'accessProfile:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($perfil === 'admin', fn ($q) => $q->where('is_admin', true))
            ->when(ctype_digit($perfil), fn ($q) => $q->where('is_admin', false)->where('access_profile_id', (int) $perfil))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'profiles' => AccessProfile::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('users.create', $this->formData());
    }

    public function store(StoreUserRequest $request, SyncUserAccess $access)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        DB::transaction(function () use ($data, $access) {
            $user = User::create(Arr::only($data, self::PROFILE_FIELDS));
            $access->execute($user, $data);
        });

        return redirect()->route('users.index')
            ->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('users.edit', array_merge($this->formData(), [
            'user' => $user->load('events'),
        ]));
    }

    public function update(UpdateUserRequest $request, User $user, SyncUserAccess $access)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        DB::transaction(function () use ($user, $data, $access) {
            $user->update(Arr::only($data, self::PROFILE_FIELDS));
            $access->execute($user, $data);
        });

        return redirect()->route('users.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir o próprio usuário.');
        }

        if ($user->isAdmin() && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'Não é possível excluir o último administrador.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }

    private function formData(): array
    {
        return [
            'profiles' => AccessProfile::orderBy('name')->get(['id', 'name']),
            'setores' => Setor::active()->orderBy('nome')->get(),
            'events' => Event::active()->orderByDesc('start_date')->get(['id', 'name']),
        ];
    }
}
