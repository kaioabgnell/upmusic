<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\AccessModule;
use App\Domain\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\Empresa;
use App\Models\FinanceCostItem;
use App\Services\Finance\FinanceExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Contas bancárias (specs/26): cadastro + vínculo nos pagamentos de custo e nas receitas. */
class BankAccountTest extends FinanceTestCase
{
    private function empresa(string $name = 'Up Music Eventos', string $document = '11222333000181'): Empresa
    {
        return Empresa::create(['corporate_name' => $name, 'document' => $document]);
    }

    private function account(string $name = 'Operacional Itaú', bool $active = true, array $attributes = []): BankAccount
    {
        return BankAccount::create($attributes + [
            'bank' => '341 - Itaú Unibanco',
            'name' => $name,
            'agency' => '1234',
            'account_number' => '56789-0',
            'account_type' => BankAccountType::Corrente,
            'active' => $active,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'bank' => '341 - Itaú Unibanco',
            'name' => 'Operacional Itaú',
            'agency' => '1234',
            'account_number' => '56789-0',
            'account_type' => 'corrente',
            'active' => '1',
        ];
    }

    private function costItem(): FinanceCostItem
    {
        return $this->sheet()->costItems()->create(['description' => 'Gerador', 'unit_actual' => 1000, 'quantity' => 1, 'daily_count' => 1]);
    }

    // ---- Cadastro ----------------------------------------------------------

    public function test_admin_cria_conta_para_varias_empresas_edita_e_lista(): void
    {
        $admin = $this->user();
        $up = $this->empresa();
        $produtora = $this->empresa('Up Produtora', '45723174000110');

        $this->actingAs($admin)
            ->post(route('bank-accounts.store'), $this->payload(['name' => ' Operacional Itaú ', 'empresas' => [$up->id, $produtora->id]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bank-accounts.index'));

        $account = BankAccount::firstOrFail();
        $this->assertSame('Operacional Itaú', $account->name);
        $this->assertSame(BankAccountType::Corrente, $account->account_type);
        $this->assertEqualsCanonicalizing([$up->id, $produtora->id], $account->empresas->pluck('id')->all());
        $this->assertSame('Operacional Itaú (Ag. 1234 · 56789-0)', $account->label);

        $this->actingAs($admin)
            ->put(route('bank-accounts.update', $account), $this->payload([
                'name' => 'Reserva', 'account_type' => 'investimento', 'active' => '0', 'empresas' => [$produtora->id],
            ]))
            ->assertRedirect(route('bank-accounts.index'));

        $account->refresh();
        $this->assertSame(BankAccountType::Investimento, $account->account_type);
        $this->assertFalse($account->active);
        $this->assertSame([$produtora->id], $account->empresas->pluck('id')->all());

        $this->actingAs($admin)->get(route('bank-accounts.index', ['empresa_id' => $produtora->id]))
            ->assertOk()->assertSee('Reserva')->assertSee('Up Produtora');
        $this->actingAs($admin)->get(route('bank-accounts.index', ['empresa_id' => $up->id]))
            ->assertOk()->assertDontSee('Reserva');
    }

    public function test_exige_pelo_menos_uma_empresa_e_campos_obrigatorios(): void
    {
        $this->actingAs($this->user())
            ->post(route('bank-accounts.store'), ['account_type' => 'cripto'])
            ->assertSessionHasErrors(['empresas', 'bank', 'name', 'agency', 'account_number', 'account_type']);

        $this->assertSame(0, BankAccount::count());
    }

    public function test_banco_agencia_e_conta_sao_unicos_so_entre_as_nao_excluidas(): void
    {
        $admin = $this->user();
        $empresa = $this->empresa();
        $old = $this->account();

        $this->actingAs($admin)
            ->post(route('bank-accounts.store'), $this->payload(['name' => 'Outra', 'empresas' => [$empresa->id]]))
            ->assertSessionHasErrors('account_number');

        // Mesmo número em outra agência é outra conta.
        $this->actingAs($admin)
            ->post(route('bank-accounts.store'), $this->payload(['agency' => '9999', 'empresas' => [$empresa->id]]))
            ->assertSessionHasNoErrors();

        $old->delete();

        $this->actingAs($admin)
            ->post(route('bank-accounts.store'), $this->payload(['empresas' => [$empresa->id]]))
            ->assertSessionHasNoErrors();
    }

    public function test_exclusao_bloqueada_quando_em_uso_e_soft_delete_quando_livre(): void
    {
        $admin = $this->user();
        $used = $this->account();
        $free = $this->account('Poupança', true, ['account_number' => '111']);

        $this->costItem()->payments()->create([
            'finance_payment_source_id' => $this->source()->id, 'bank_account_id' => $used->id, 'amount' => 100,
        ]);

        $this->actingAs($admin)->from(route('bank-accounts.index'))
            ->delete(route('bank-accounts.destroy', $used))
            ->assertRedirect(route('bank-accounts.index'))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($used);

        $this->actingAs($admin)->delete(route('bank-accounts.destroy', $free))->assertSessionHas('success');
        $this->assertSoftDeleted($free);
    }

    /** specs/28: o módulo do perfil decide; "Eventos visíveis" não esconde cadastro que não é de evento. */
    public function test_gerenciar_depende_do_modulo_contas_bancarias(): void
    {
        $this->actingAs($this->user('usuario'))->get(route('bank-accounts.index'))->assertForbidden();

        $scoped = $this->user('sem-acesso', modules: [AccessModule::BankAccounts]);
        $scoped->events()->attach($this->event());
        $this->actingAs($scoped->fresh())->get(route('bank-accounts.index'))->assertOk();

        $this->actingAs($this->user('coordenador'))->get(route('bank-accounts.create'))->assertOk();
    }

    // ---- Pagamentos de custo (de onde sai) ---------------------------------

    public function test_pagamento_registra_a_conta_de_saida_e_a_lista_mostra(): void
    {
        $admin = $this->user();
        $account = $this->account();
        $item = $this->costItem();

        $this->actingAs($admin)
            ->postJson(route('finance.payments.store', $item), [
                'finance_payment_source_id' => $this->source()->id, 'bank_account_id' => $account->id, 'amount' => '250,00',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.bank_account_id', $account->id)
            ->assertJsonPath('payment.bank_account', $account->label);

        $this->actingAs($admin)->getJson(route('finance.payments.index', $item))
            ->assertOk()->assertJsonPath('payments.0.bank_account', $account->label);
    }

    public function test_conta_e_opcional_no_pagamento(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('finance.payments.store', $this->costItem()), [
                'finance_payment_source_id' => $this->source()->id, 'amount' => '10,00',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.bank_account_id', null);
    }

    public function test_conta_inativa_nao_entra_em_pagamento_novo_mas_continua_no_que_ja_a_tem(): void
    {
        $admin = $this->user();
        $inactive = $this->account('Antiga', false);
        $item = $this->costItem();
        $source = $this->source();

        $this->actingAs($admin)
            ->postJson(route('finance.payments.store', $item), [
                'finance_payment_source_id' => $source->id, 'bank_account_id' => $inactive->id, 'amount' => 10,
            ])
            ->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');

        $payment = $item->payments()->create([
            'finance_payment_source_id' => $source->id, 'bank_account_id' => $inactive->id, 'amount' => 10,
        ]);

        // A troca de outro campo reenvia a conta inativa que o pagamento já tem — continua válida.
        $this->actingAs($admin)
            ->putJson(route('finance.payments.update', $payment), [
                'finance_payment_source_id' => $source->id, 'bank_account_id' => $inactive->id, 'amount' => 10, 'notes' => 'PIX',
            ])
            ->assertOk()->assertJsonPath('payment.bank_account', $inactive->label);
    }

    public function test_troca_e_limpa_a_conta_de_um_pagamento_ja_lancado(): void
    {
        $admin = $this->user();
        $account = $this->account();
        $source = $this->source();
        $payment = $this->costItem()->payments()->create(['finance_payment_source_id' => $source->id, 'amount' => 80]);

        $payload = ['finance_payment_source_id' => $source->id, 'amount' => 80];

        $this->actingAs($admin)->putJson(route('finance.payments.update', $payment), $payload + ['bank_account_id' => $account->id])
            ->assertOk()->assertJsonPath('payment.bank_account_id', $account->id);

        $this->actingAs($admin)->putJson(route('finance.payments.update', $payment), $payload + ['bank_account_id' => null])
            ->assertOk()->assertJsonPath('payment.bank_account_id', null);

        $this->assertEquals(80, $payment->refresh()->amount);
    }

    public function test_tela_de_custos_oferece_ativas_e_a_inativa_ja_usada(): void
    {
        $admin = $this->user();
        $event = $this->event();
        $sheet = $this->sheet($event);
        $this->account('Conta ativa');
        $usedInactive = $this->account('Conta encerrada', false, ['account_number' => '222']);
        $this->account('Conta esquecida', false, ['account_number' => '333']);

        $sheet->costItems()->create(['description' => 'Palco'])->payments()->create([
            'finance_payment_source_id' => $this->source()->id, 'bank_account_id' => $usedInactive->id, 'amount' => 5,
        ]);

        $this->actingAs($admin)->get(route('finance.costs.index', $event))
            ->assertOk()
            ->assertSee('Conta ativa')
            ->assertSee('Conta encerrada (Ag. 1234 · 222) (inativa)')
            ->assertDontSee('Conta esquecida');
    }

    // ---- Receitas (para onde entra) ----------------------------------------

    public function test_receita_recebe_e_limpa_a_conta_de_entrada(): void
    {
        $admin = $this->user();
        $account = $this->account();
        $revenue = $this->sheet()->revenues()->create(['category' => 'patrocinio']);

        $payload = ['category' => 'patrocinio', 'bank_account_id' => $account->id];

        $this->actingAs($admin)->putJson(route('finance.revenues.update', $revenue), $payload)
            ->assertOk()->assertJsonPath('bank_account_id', $account->id);

        $this->actingAs($admin)->putJson(route('finance.revenues.update', $revenue), ['bank_account_id' => null] + $payload)
            ->assertOk()->assertJsonPath('bank_account_id', null);

        $inactive = $this->account('Antiga', false, ['account_number' => '999']);
        $this->actingAs($admin)->putJson(route('finance.revenues.update', $revenue), ['bank_account_id' => $inactive->id] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');
    }

    public function test_exportacao_traz_a_conta_da_receita_na_ultima_coluna(): void
    {
        $account = $this->account();
        $sheet = $this->sheet();
        $sheet->revenues()->create(['category' => 'patrocinio', 'bank_account_id' => $account->id]);

        $ws = IOFactory::load(app(FinanceExportService::class)->toXlsx($sheet->refresh()))->getSheetByName('RECEITAS');

        $this->assertSame('CONTA BANCÁRIA', $ws->getCell('I3')->getValue());
        $values = array_map(fn ($r) => $r[0], $ws->rangeToArray('I4:I20'));
        $this->assertContains($account->label, $values);
    }
}
