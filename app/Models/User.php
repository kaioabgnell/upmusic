<?php

namespace App\Models;

use App\Domain\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'setor_id', 'phone', 'avatar_path', 'active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'active' => 'boolean',
    ];

    // Relacionamentos -------------------------------------------------------

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'user_board');
    }

    public function assignedCards(): HasMany
    {
        return $this->hasMany(Card::class, 'assignee_id');
    }

    // Perfis ----------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCoordenador(): bool
    {
        return $this->role === UserRole::Coordenador;
    }

    public function canAccessBoard(Board $board): bool
    {
        if ($this->isAdmin() || $this->isCoordenador()) {
            return true;
        }

        return $this->boards()->whereKey($board->getKey())->exists();
    }

    /**
     * Iniciais do nome e sobrenome (ex.: "João Silva" -> "JS"). Sem sobrenome, só a primeira letra.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $first = mb_strtoupper(mb_substr($parts[0], 0, 1));

        return count($parts) > 1 ? $first.mb_strtoupper(mb_substr(end($parts), 0, 1)) : $first;
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            // Servido pela rota avatar.show (não pela URL /storage), pois o symlink não existe em
            // produção. O `v` invalida o cache do navegador quando a foto muda (ver ProfileController).
            get: fn () => $this->avatar_path
                ? route('avatar.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
                : null,
        );
    }
}
