<?php

namespace App\Models;

use App\Domain\Enums\AccessModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perfil de acesso (specs/28): nome + módulos ligados + quadros ligados. Todo usuário que não é admin
 * tem exatamente um. Admin ignora o perfil.
 */
class AccessProfile extends Model
{
    use HasFactory;

    public const NAME_MAX = 60;

    protected $fillable = ['name'];

    public function modules(): HasMany
    {
        return $this->hasMany(AccessProfileModule::class);
    }

    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'access_profile_board')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Módulos ligados, ignorando valores gravados que não existem mais no enum.
     *
     * @return list<AccessModule>
     */
    public function moduleList(): array
    {
        return $this->modules
            ->map(fn (AccessProfileModule $m) => AccessModule::tryFrom($m->module))
            ->filter()
            ->values()
            ->all();
    }

    public function hasModule(AccessModule $module): bool
    {
        return $this->modules->contains('module', $module->value);
    }

    /**
     * Nome livre a partir de `$base`: corta em 60 caracteres e, se já existir, acrescenta "(2)", "(3)"…
     * Usado ao duplicar perfil e na migração dos papéis antigos (specs/28 §7.1 e §9).
     */
    public static function uniqueName(string $base): string
    {
        $base = trim(mb_substr($base, 0, self::NAME_MAX));
        $name = $base;

        for ($n = 2; static::query()->where('name', $name)->exists(); $n++) {
            $suffix = " ({$n})";
            $name = rtrim(mb_substr($base, 0, self::NAME_MAX - mb_strlen($suffix))).$suffix;
        }

        return $name;
    }
}
