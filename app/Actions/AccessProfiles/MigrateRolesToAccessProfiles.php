<?php

namespace App\Actions\AccessProfiles;

use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Troca os papéis fixos (admin/coordenador/usuario) por Administrador + Perfil de acesso, sem mudar o
 * acesso de ninguém no dia da troca (specs/28 §9).
 *
 * Lê os dados do banco em que roda — a produção pode ser diferente da base local — e só usa o query
 * builder sobre as colunas antigas (`users.role`, `users.can_access_rentals`, `user_board`), que
 * deixam de existir depois da migration seguinte. Usuários na lixeira entram também, para que
 * restaurar um usuário funcione.
 *
 * Idempotente: quem já tem `is_admin` ou perfil é ignorado, então rodar de novo não cria nada.
 */
class MigrateRolesToAccessProfiles
{
    /** Coordenador sem eventos vinculados: tudo o que ele acessava, menos o que era só do Admin. */
    public const COORDINATOR_MODULES = [
        AccessModule::BoardsConfigure,
        AccessModule::Setores,
        AccessModule::Empresas,
        AccessModule::CostCenters,
        AccessModule::BankAccounts,
        AccessModule::FixedCosts,
        AccessModule::Fornecedores,
        AccessModule::FornecedorCategorias,
        AccessModule::Events,
        AccessModule::Templates,
        AccessModule::Finance,
        AccessModule::Prices,
        AccessModule::Rentals,
    ];

    /**
     * Coordenador restrito por evento (specs/20): via todos os quadros de evento, Eventos, Financeiro
     * dos Eventos e Banco de Preços. Os outros cadastros, Locações e Custos fixos ficavam escondidos.
     */
    public const SCOPED_COORDINATOR_MODULES = [
        AccessModule::BoardsConfigure,
        AccessModule::Events,
        AccessModule::Finance,
        AccessModule::Prices,
    ];

    /** Usuário comum: Fornecedores e Banco de Preços eram abertos a todos (specs/28 §9.1). */
    public const USER_MODULES = [
        AccessModule::Fornecedores,
        AccessModule::Prices,
    ];

    /** @return array<string, int> nome do perfil => usuários vinculados nesta execução */
    public function execute(): array
    {
        if (! Schema::hasColumn('users', 'role')) {
            return [];
        }

        return DB::transaction(function () {
            $summary = [];

            DB::table('users')->where('role', 'admin')->where('is_admin', false)
                ->update(['is_admin' => true, 'access_profile_id' => null]);

            $this->migrateCoordinators($summary);
            $this->migrateUsers($summary);

            return $summary;
        });
    }

    private function migrateCoordinators(array &$summary): void
    {
        $ids = $this->pending('coordenador')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $scoped = DB::table('event_user')->whereIn('user_id', $ids)->distinct()->pluck('user_id');
        $full = $ids->diff($scoped);

        if ($full->isNotEmpty()) {
            $this->assign('Coordenador', self::COORDINATOR_MODULES, collect(), $full, $summary);
        }
        if ($scoped->isNotEmpty()) {
            $this->assign('Coordenador restrito por evento', self::SCOPED_COORDINATOR_MODULES, collect(), $scoped, $summary);
        }
    }

    /**
     * Usuários comuns agrupados pela combinação "quadros em `user_board` + Locações": cada combinação
     * vira um perfil com o nome dos quadros (specs/28 §9, passo 3).
     */
    private function migrateUsers(array &$summary): void
    {
        $hasRentalsFlag = Schema::hasColumn('users', 'can_access_rentals');
        $users = $this->pending('usuario')->get($hasRentalsFlag ? ['id', 'can_access_rentals'] : ['id']);
        if ($users->isEmpty()) {
            return;
        }

        // Quadro de custos fixos nunca abria pelo vínculo do usuário (specs/27 §1.2): não entra no perfil.
        $boardsByUser = Schema::hasTable('user_board')
            ? DB::table('user_board')
                ->join('boards', 'boards.id', '=', 'user_board.board_id')
                ->whereIn('user_board.user_id', $users->pluck('id'))
                ->where('boards.fixed_costs', false)
                ->get(['user_board.user_id', 'user_board.board_id'])
                ->groupBy('user_id')
                ->map(fn (Collection $rows) => $rows->pluck('board_id')->map(fn ($id) => (int) $id)->sort()->values())
            : collect();

        $groups = $users->groupBy(function ($user) use ($boardsByUser, $hasRentalsFlag) {
            $boards = $boardsByUser->get($user->id, collect())->implode(',');
            $rentals = $hasRentalsFlag && $user->can_access_rentals ? 1 : 0;

            return "{$boards}|{$rentals}";
        });

        foreach ($groups as $key => $members) {
            [$boardList, $rentals] = explode('|', $key);
            $boardIds = collect($boardList === '' ? [] : explode(',', $boardList))->map(fn ($id) => (int) $id);

            $names = DB::table('boards')->whereIn('id', $boardIds)->whereNull('deleted_at')
                ->orderBy('name')->pluck('name');
            $name = $names->isEmpty() ? 'Usuário' : $names->implode(' + ');

            $modules = self::USER_MODULES;
            if ($rentals === '1') {
                $modules[] = AccessModule::Rentals;
                $name .= ' + Locações';
            }

            $this->assign($name, $modules, $boardIds, $members->pluck('id'), $summary);
        }
    }

    private function pending(string $role)
    {
        return DB::table('users')
            ->where('role', $role)
            ->where('is_admin', false)
            ->whereNull('access_profile_id');
    }

    /** @param list<AccessModule> $modules */
    private function assign(string $name, array $modules, Collection $boardIds, Collection $userIds, array &$summary): void
    {
        $now = now();
        $name = AccessProfile::uniqueName($name);

        $profileId = DB::table('access_profiles')->insertGetId([
            'name' => $name, 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('access_profile_modules')->insert(array_map(fn (AccessModule $m) => [
            'access_profile_id' => $profileId, 'module' => $m->value, 'created_at' => $now, 'updated_at' => $now,
        ], $modules));

        if ($boardIds->isNotEmpty()) {
            DB::table('access_profile_board')->insert($boardIds->map(fn (int $boardId) => [
                'access_profile_id' => $profileId, 'board_id' => $boardId, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        }

        DB::table('users')->whereIn('id', $userIds)->update(['access_profile_id' => $profileId]);

        $summary[$name] = $userIds->count();
    }
}
