<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_id', 'name', 'color', 'position', 'is_final', 'is_entry',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_final' => 'boolean',
        'is_entry' => 'boolean',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }

    /**
     * Aprovadores configurados para esta coluna (sempre admins — ver specs/17). A simples existência
     * de aprovadores já marca a coluna como "exige aprovação para avançar", sem flag própria.
     */
    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_column_approvers')->withTimestamps();
    }

    public function requiresApproval(): bool
    {
        return $this->relationLoaded('approvers')
            ? $this->approvers->isNotEmpty()
            : $this->approvers()->exists();
    }

    public function isApproverFor(?User $user): bool
    {
        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        return $this->relationLoaded('approvers')
            ? $this->approvers->contains('id', $user->id)
            : $this->approvers()->whereKey($user->id)->exists();
    }

    /**
     * Próxima coluna do mesmo quadro por posição — usada pela aprovação (specs/17), que não tem
     * seleção de coluna de destino: aprovar sempre avança para a próxima etapa configurada.
     */
    public function nextColumn(): ?self
    {
        return $this->board->columns()->where('position', '>', $this->position)->orderBy('position')->first();
    }
}
