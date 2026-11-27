<?php

namespace Tests\Feature\AccessProfiles;

use App\Actions\AccessProfiles\MigrateRolesToAccessProfiles;
use App\Models\AccessProfile;
use App\Models\Board;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migração dos papéis antigos para perfis de acesso (specs/28 §9).
 *
 * Sem RefreshDatabase: recriar as colunas antigas é DDL, que no MySQL fecha a transação do teste.
 * Por isso o próprio teste recria o modelo antigo no setUp e, no tearDown, remove essas colunas e
 * limpa as tabelas que usou, deixando o banco como os outros testes esperam.
 */
class MigrateRolesToAccessProfilesTest extends TestCase
{
    private const TABLES = [
        'access_profile_board', 'access_profile_modules', 'event_user', 'users', 'access_profiles',
        'board_columns', 'boards', 'events',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
        }

        $this->truncate();

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('usuario');
            $table->boolean('can_access_rentals')->default(false);
        });
        Schema::create('user_board', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('board_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('user_board');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'can_access_rentals']));
        $this->truncate();

        parent::tearDown();
    }

    private function truncate(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
    }

    private function legacyUser(string $role, array $attributes = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('users')->insertGetId($attributes + [
            'name' => "Pessoa {$n}", 'email' => "pessoa{$n}@teste.local", 'password' => 'x',
            'role' => $role, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function profileOf(int $userId): ?AccessProfile
    {
        $id = DB::table('users')->where('id', $userId)->value('access_profile_id');

        return $id ? AccessProfile::with('modules', 'boards')->find($id) : null;
    }

    private function modulesOf(int $userId): array
    {
        return $this->profileOf($userId)->modules->pluck('module')->sort()->values()->all();
    }

    public function test_cada_papel_vira_o_pacote_de_acesso_de_hoje(): void
    {
        $orcamentos = Board::create(['name' => 'Orçamentos', 'active' => true]);
        $juridico = Board::create(['name' => 'Jurídico', 'active' => true]);
        $fixed = Board::create(['name' => 'Custos Fixos', 'fixed_costs' => true, 'active' => true]);
        $event = DB::table('events')->insertGetId(['name' => 'Copa', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'active' => true]);

        $admin = $this->legacyUser('admin');
        $coord = $this->legacyUser('coordenador');
        $restrito = $this->legacyUser('coordenador');
        DB::table('event_user')->insert(['event_id' => $event, 'user_id' => $restrito]);
        $orc1 = $this->legacyUser('usuario');
        $orc2 = $this->legacyUser('usuario', ['deleted_at' => now()]);
        $jur = $this->legacyUser('usuario');
        $loc = $this->legacyUser('usuario', ['can_access_rentals' => true]);
        $nada = $this->legacyUser('usuario');
        DB::table('user_board')->insert([
            ['user_id' => $orc1, 'board_id' => $orcamentos->id],
            ['user_id' => $orc1, 'board_id' => $fixed->id],      // o vínculo nunca abria custos fixos
            ['user_id' => $orc2, 'board_id' => $orcamentos->id],
            ['user_id' => $jur, 'board_id' => $juridico->id],
            ['user_id' => $loc, 'board_id' => $orcamentos->id],
        ]);

        $summary = app(MigrateRolesToAccessProfiles::class)->execute();

        $this->assertTrue((bool) DB::table('users')->where('id', $admin)->value('is_admin'));
        $this->assertNull($this->profileOf($admin));

        $this->assertSame('Coordenador', $this->profileOf($coord)->name);
        $this->assertCount(13, $this->modulesOf($coord));
        $this->assertNotContains('bids', $this->modulesOf($coord));
        $this->assertNotContains('captures', $this->modulesOf($coord));
        $this->assertNotContains('finance_settings', $this->modulesOf($coord));

        $this->assertSame('Coordenador restrito por evento', $this->profileOf($restrito)->name);
        $this->assertSame(['boards_configure', 'events', 'finance', 'prices'], $this->modulesOf($restrito));
        $this->assertSame(1, DB::table('event_user')->where('user_id', $restrito)->count());

        $orcProfile = $this->profileOf($orc1);
        $this->assertSame('Orçamentos', $orcProfile->name);
        $this->assertSame($orcProfile->id, $this->profileOf($orc2)->id, 'usuário na lixeira entra no mesmo grupo');
        $this->assertSame([$orcamentos->id], $orcProfile->boards->pluck('id')->all());
        $this->assertSame(['fornecedores', 'prices'], $this->modulesOf($orc1));

        $this->assertSame('Jurídico', $this->profileOf($jur)->name);
        $this->assertSame('Orçamentos + Locações', $this->profileOf($loc)->name);
        $this->assertSame(['fornecedores', 'prices', 'rentals'], $this->modulesOf($loc));
        $this->assertSame('Usuário', $this->profileOf($nada)->name);
        $this->assertSame(0, $this->profileOf($nada)->boards->count());

        $this->assertSame(2, $summary['Orçamentos']);
    }

    public function test_rodar_de_novo_nao_duplica_e_nome_repetido_ganha_sufixo(): void
    {
        AccessProfile::create(['name' => 'Coordenador']);
        $coord = $this->legacyUser('coordenador');

        app(MigrateRolesToAccessProfiles::class)->execute();
        app(MigrateRolesToAccessProfiles::class)->execute();

        $this->assertSame('Coordenador (2)', $this->profileOf($coord)->name);
        $this->assertSame(2, AccessProfile::count());
    }
}
