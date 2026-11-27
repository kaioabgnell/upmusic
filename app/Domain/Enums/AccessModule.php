<?php

namespace App\Domain\Enums;

/**
 * Módulos que um Perfil de acesso liga ou desliga (specs/28 §3). Ligado = ver, criar, editar e
 * excluir tudo do módulo. O valor é o que fica gravado em `access_profile_modules.module`: renomear
 * um valor exige migration de dados.
 *
 * Os quadros de departamento não estão aqui — cada quadro é um item próprio do perfil
 * (`access_profile_board`, specs/28 §4).
 */
enum AccessModule: string
{
    case BoardsConfigure = 'boards_configure';
    case Setores = 'setores';
    case Empresas = 'empresas';
    case CostCenters = 'cost_centers';
    case BankAccounts = 'bank_accounts';
    case FixedCosts = 'fixed_costs';
    case Fornecedores = 'fornecedores';
    case FornecedorCategorias = 'fornecedor_categorias';
    case Events = 'events';
    case Templates = 'templates';
    case Finance = 'finance';
    case Prices = 'prices';
    case FinanceSettings = 'finance_settings';
    case Captures = 'captures';
    case Rentals = 'rentals';
    case Bids = 'bids';

    public function label(): string
    {
        return match ($this) {
            self::BoardsConfigure => 'Configurar quadros',
            self::Setores => 'Setores',
            self::Empresas => 'Empresas',
            self::CostCenters => 'Centros de custo',
            self::BankAccounts => 'Contas bancárias',
            self::FixedCosts => 'Custos fixos',
            self::Fornecedores => 'Fornecedores',
            self::FornecedorCategorias => 'Categorias de fornecedor',
            self::Events => 'Eventos',
            self::Templates => 'Templates de Cards',
            self::Finance => 'Financeiro dos Eventos',
            self::Prices => 'Banco de Preços',
            self::FinanceSettings => 'Config. Financeiro',
            self::Captures => 'Captura rápida',
            self::Rentals => 'Locações',
            self::Bids => 'Licitações',
        };
    }

    public function group(): AccessModuleGroup
    {
        return match ($this) {
            self::BoardsConfigure => AccessModuleGroup::Quadros,
            self::Setores, self::Empresas, self::CostCenters, self::BankAccounts, self::FixedCosts,
            self::Fornecedores, self::FornecedorCategorias, self::Events, self::Templates => AccessModuleGroup::Cadastros,
            self::Finance, self::Prices, self::FinanceSettings => AccessModuleGroup::Financeiro,
            self::Captures, self::Rentals, self::Bids => AccessModuleGroup::Operacao,
        };
    }

    /** Mesmos ícones do menu lateral, para a pessoa reconhecer o item. */
    public function icon(): string
    {
        return match ($this) {
            self::BoardsConfigure => 'fa-screwdriver-wrench',
            self::Setores => 'fa-sitemap',
            self::Empresas => 'fa-building',
            self::CostCenters => 'fa-diagram-project',
            self::BankAccounts => 'fa-building-columns',
            self::FixedCosts => 'fa-arrows-rotate',
            self::Fornecedores => 'fa-truck-field',
            self::FornecedorCategorias => 'fa-tags',
            self::Events => 'fa-calendar-days',
            self::Templates => 'fa-clone',
            self::Finance => 'fa-file-invoice-dollar',
            self::Prices => 'fa-tag',
            self::FinanceSettings => 'fa-sliders',
            self::Captures => 'fa-bolt',
            self::Rentals => 'fa-boxes-stacked',
            self::Bids => 'fa-gavel',
        };
    }

    /** Dica fixa exibida abaixo do módulo na tela do perfil. */
    public function hint(): ?string
    {
        return match ($this) {
            self::BoardsConfigure => 'Dá acesso a todos os quadros, inclusive os criados depois.',
            self::FixedCosts => 'Inclui o quadro de custos fixos.',
            self::Finance => 'Fecha a prestação de contas. Reabrir é só do Administrador.',
            self::Rentals => 'Inclui encerrar pedidos, cadastrar itens e ver investimento.',
            default => null,
        };
    }

    /** @return array<string, list<self>> valor do grupo => módulos, na ordem de `AccessModuleGroup`. */
    public static function grouped(): array
    {
        $groups = [];
        foreach (AccessModuleGroup::cases() as $group) {
            $groups[$group->value] = array_values(array_filter(
                self::cases(),
                fn (self $module) => $module->group() === $group,
            ));
        }

        return $groups;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
