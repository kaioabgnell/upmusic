<?php

namespace App\Models;

use App\Domain\Enums\AccessModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'setor_id', 'name', 'description', 'color', 'icon', 'position', 'active', 'allows_supplier_form', 'feeds_finance', 'fixed_costs',
    ];

    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
        'allows_supplier_form' => 'boolean',
        'feeds_finance' => 'boolean',
        'fixed_costs' => 'boolean',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(BoardField::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** Perfis de acesso que ligam este quadro (specs/28 §4). */
    public function accessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(AccessProfile::class, 'access_profile_board')->withTimestamps();
    }

    public function externalForms(): HasMany
    {
        return $this->hasMany(ExternalForm::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Custos fixos que geram cards neste quadro (specs/27). */
    public function fixedCostItems(): HasMany
    {
        return $this->hasMany(FixedCost::class);
    }

    /** Quadros de custos fixos (specs/27 §1.1). */
    public function scopeFixedCosts($query)
    {
        return $query->where('fixed_costs', true);
    }

    /** Quadros dos eventos/processos — tudo o que não é quadro de custos fixos. */
    public function scopeEventBoards($query)
    {
        return $query->where('fixed_costs', false);
    }

    /**
     * Quadros que o usuário enxerga no menu e nas listas — a mesma régua de `User::canAccessBoard()`
     * (specs/28 §4): Admin vê todos; quadros de evento pelo perfil ("Configurar quadros" = todos) e
     * quadros de custos fixos pelo módulo Custos fixos.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $configureAll = $user->hasModule(AccessModule::BoardsConfigure);
        $fixedCosts = $user->hasModule(AccessModule::FixedCosts);
        $boardIds = $user->profileBoardIds();
        $fixedColumn = $query->qualifyColumn('fixed_costs');

        return $query->where(function ($q) use ($configureAll, $fixedCosts, $boardIds, $fixedColumn, $query) {
            $q->where(function ($q) use ($configureAll, $boardIds, $fixedColumn, $query) {
                $q->where($fixedColumn, false);
                if (! $configureAll) {
                    $q->whereIn($query->qualifyColumn('id'), $boardIds);
                }
            });

            if ($fixedCosts) {
                $q->orWhere($fixedColumn, true);
            }
        });
    }
}
