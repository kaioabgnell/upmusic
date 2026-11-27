<?php

namespace App\Actions\AccessProfiles;

use App\Models\AccessProfile;
use App\Models\Board;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou atualiza um perfil de acesso com os módulos e quadros ligados (specs/28 §7.1). Vale na hora
 * para todos os usuários do perfil: o acesso é lido a cada requisição, sem cache entre elas.
 */
class SaveAccessProfile
{
    /** @param array{name: string, modules?: list<string>, boards?: list<int|string>} $data */
    public function execute(AccessProfile $profile, array $data): AccessProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile->fill(['name' => $data['name']])->save();

            $this->syncModules($profile, collect($data['modules'] ?? [])->unique()->values());
            $this->syncBoards($profile, collect($data['boards'] ?? [])->map(fn ($id) => (int) $id));

            return $profile;
        });
    }

    private function syncModules(AccessProfile $profile, Collection $values): void
    {
        $profile->modules()->whereNotIn('module', $values->all())->delete();

        $existing = $profile->modules()->pluck('module');
        foreach ($values->diff($existing) as $value) {
            $profile->modules()->create(['module' => $value]);
        }
    }

    /**
     * Só mexe nos quadros que a tela mostra (ativos, de evento). Vínculos com quadros inativos ficam
     * gravados para quando o quadro voltar (specs/28 §4).
     */
    private function syncBoards(AccessProfile $profile, Collection $submitted): void
    {
        $shown = Board::active()->eventBoards()->pluck('id');
        $on = $submitted->intersect($shown)->values();

        $profile->boards()->detach($shown->diff($on)->values()->all());
        $profile->boards()->syncWithoutDetaching($on->all());
    }
}
