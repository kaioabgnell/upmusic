<?php

namespace App\Http\Controllers;

use App\Actions\AccessProfiles\DeleteAccessProfile;
use App\Actions\AccessProfiles\DuplicateAccessProfile;
use App\Actions\AccessProfiles\SaveAccessProfile;
use App\Domain\Enums\AccessModule;
use App\Http\Requests\StoreAccessProfileRequest;
use App\Http\Requests\UpdateAccessProfileRequest;
use App\Models\AccessProfile;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Perfis de acesso (specs/28 §7.1) — Administração, só Admin. */
class AccessProfileController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AccessProfile::class);

        $profiles = AccessProfile::query()
            ->withCount(['users', 'boards'])
            ->withCount(['modules' => fn ($q) => $q->whereIn('module', AccessModule::values())])
            ->withExists(['modules as configures_all_boards' => fn ($q) => $q->where('module', AccessModule::BoardsConfigure->value)])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('access-profiles.index', [
            'profiles' => $profiles,
            'totalModules' => count(AccessModule::cases()),
        ]);
    }

    public function create()
    {
        $this->authorize('create', AccessProfile::class);

        return view('access-profiles.create', $this->formData(new AccessProfile));
    }

    public function store(StoreAccessProfileRequest $request, SaveAccessProfile $action)
    {
        $profile = $action->execute(new AccessProfile, $request->validated());

        return redirect()->route('access-profiles.index')
            ->with('success', "Perfil de acesso \"{$profile->name}\" criado.");
    }

    public function edit(AccessProfile $accessProfile)
    {
        $this->authorize('update', $accessProfile);

        return view('access-profiles.edit', $this->formData($accessProfile->load('modules', 'boards')));
    }

    public function update(UpdateAccessProfileRequest $request, AccessProfile $accessProfile, SaveAccessProfile $action)
    {
        $action->execute($accessProfile, $request->validated());

        return redirect()->route('access-profiles.index')
            ->with('success', "Perfil de acesso \"{$accessProfile->name}\" atualizado.");
    }

    public function destroy(AccessProfile $accessProfile, DeleteAccessProfile $action)
    {
        $this->authorize('delete', $accessProfile);

        try {
            $action->execute($accessProfile);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('access-profiles.index')
            ->with('success', "Perfil de acesso \"{$accessProfile->name}\" excluído.");
    }

    public function duplicate(AccessProfile $accessProfile, DuplicateAccessProfile $action)
    {
        $this->authorize('create', AccessProfile::class);

        $copy = $action->execute($accessProfile);

        return redirect()->route('access-profiles.edit', $copy)
            ->with('success', 'Perfil duplicado. Ajuste o nome e os acessos.');
    }

    private function formData(AccessProfile $profile): array
    {
        return [
            'profile' => $profile,
            'groups' => AccessModule::grouped(),
            // Quadro de custos fixos não aparece: vem com o módulo Custos fixos (specs/28 §4).
            'boards' => Board::active()->eventBoards()->orderBy('position')->orderBy('name')->get(['id', 'name', 'icon']),
        ];
    }
}
