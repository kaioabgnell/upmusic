<?php

namespace App\Models;

use App\Domain\Enums\AccessModule;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'is_admin', 'access_profile_id', 'setor_id', 'phone', 'avatar_path', 'active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'active' => 'boolean',
    ];

    // Relacionamentos -------------------------------------------------------

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    /** Perfil de acesso (specs/28). Nulo para o Administrador. */
    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }

    /** "Eventos visíveis: somente os selecionados" (specs/28 §6). Vazio = vê todos os eventos. */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user');
    }

    public function assignedCards(): HasMany
    {
        return $this->hasMany(Card::class, 'assignee_id');
    }

    /** Notificações internas do sino (specs/22), da mais recente para a mais antiga. */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest('id');
    }

    // Acesso (specs/28) -----------------------------------------------------

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Módulo ligado no perfil de acesso. Admin tem todos. É a régua do middleware `module`, do Gate
     * `module` (menu) e das Policies.
     */
    public function hasModule(AccessModule $module): bool
    {
        return $this->isAdmin() || in_array($module->value, $this->moduleValues(), true);
    }

    /** @return list<string> valores de `AccessModule` ligados no perfil */
    public function moduleValues(): array
    {
        if ($this->isAdmin() || ! $this->access_profile_id) {
            return [];
        }

        return $this->accessCached('modules', fn () => DB::table('access_profile_modules')
            ->where('access_profile_id', $this->access_profile_id)
            ->pluck('module')
            ->all());
    }

    /** Quadros ligados um a um no perfil (sem contar "Configurar quadros"). */
    public function profileBoardIds(): Collection
    {
        if (! $this->access_profile_id) {
            return collect();
        }

        return $this->accessCached('boards', fn () => DB::table('access_profile_board')
            ->where('access_profile_id', $this->access_profile_id)
            ->pluck('board_id')
            ->map(fn ($id) => (int) $id));
    }

    /**
     * Régua única de acesso a quadro (specs/28 §4) — escrita, menu e Kanban:
     * Admin → sim; quadro de custos fixos → módulo Custos fixos; "Configurar quadros" → todos os
     * outros; senão, o quadro precisa estar ligado no perfil.
     */
    public function canAccessBoard(Board $board): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($board->fixed_costs) {
            return $this->hasModule(AccessModule::FixedCosts);
        }
        if ($this->hasModule(AccessModule::BoardsConfigure)) {
            return true;
        }

        return $this->profileBoardIds()->contains($board->getKey());
    }

    /**
     * É responsável por algum card deste quadro (specs/22). Dá **leitura** do quadro/card mesmo sem o
     * quadro no perfil: sem isso, atribuir alguém de outro departamento como responsável criava um
     * responsável que não recebia a notificação nem conseguia abrir o próprio card.
     *
     * Deliberadamente separado de `canAccessBoard()`, que continua sendo a régua de escrita
     * (mover, transferir, editar, excluir) e do menu de quadros.
     */
    public function isAssignedOnBoard(Board $board): bool
    {
        return $this->assignedCards()->where('board_id', $board->getKey())->exists();
    }

    /**
     * "Eventos visíveis: somente os selecionados" (specs/28 §6): usuário que não é admin com ao menos
     * um evento vinculado.
     */
    public function isEventScoped(): bool
    {
        return ! $this->isAdmin() && $this->linkedEventIds()->isNotEmpty();
    }

    /**
     * Fonte única da verdade do escopo por evento:
     * - null  → sem restrição (Admin ou "Todos os eventos");
     * - Collection de event_id → restrito a esses eventos.
     */
    public function allowedEventIds(): ?Collection
    {
        return $this->isEventScoped() ? $this->linkedEventIds() : null;
    }

    /** Descarta o que foi lido nesta requisição (após mudar perfil, eventos ou Administrador). */
    public function flushAccessCache(): void
    {
        foreach (['modules', 'boards', 'events'] as $key) {
            request()->attributes->remove($this->accessCacheKey($key));
        }
    }

    /** Usuários com um módulo ligado (Admin incluído). */
    public function scopeWithModule(Builder $query, AccessModule $module): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('is_admin', true)
            ->orWhereIn('access_profile_id', AccessProfileModule::query()
                ->where('module', $module->value)
                ->select('access_profile_id')));
    }

    /** Usuários que passam em `canAccessBoard($board)`, em uma consulta só. */
    public function scopeWithBoardAccess(Builder $query, Board $board): Builder
    {
        if ($board->fixed_costs) {
            return $query->withModule(AccessModule::FixedCosts);
        }

        return $query->where(fn (Builder $q) => $q
            ->where('is_admin', true)
            ->orWhereIn('access_profile_id', AccessProfileModule::query()
                ->where('module', AccessModule::BoardsConfigure->value)
                ->select('access_profile_id'))
            ->orWhereIn('access_profile_id', DB::table('access_profile_board')
                ->where('board_id', $board->getKey())
                ->select('access_profile_id')));
    }

    private function linkedEventIds(): Collection
    {
        if (! $this->exists) {
            return collect();
        }

        return $this->accessCached('events', fn () => $this->events()->pluck('events.id'));
    }

    /**
     * Guarda o resultado na requisição atual, não na instância: o menu, as Policies e o Kanban
     * consultam o acesso várias vezes por requisição (no máximo 1 consulta por tipo), e a próxima
     * requisição já enxerga uma mudança no perfil — specs/28 §7.1 "mudança vale na hora".
     */
    private function accessCached(string $key, Closure $resolve): mixed
    {
        $attributes = request()->attributes;
        $cacheKey = $this->accessCacheKey($key);

        if (! $attributes->has($cacheKey)) {
            $attributes->set($cacheKey, $resolve());
        }

        return $attributes->get($cacheKey);
    }

    private function accessCacheKey(string $key): string
    {
        return "access.{$this->getKey()}.{$this->access_profile_id}.{$key}";
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
