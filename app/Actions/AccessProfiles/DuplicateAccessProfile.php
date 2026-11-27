<?php

namespace App\Actions\AccessProfiles;

use App\Models\AccessProfile;
use Illuminate\Support\Facades\DB;

/** "Duplicar": cria "Cópia de {nome}" com os mesmos módulos e quadros (specs/28 §7.1). */
class DuplicateAccessProfile
{
    public function execute(AccessProfile $source): AccessProfile
    {
        return DB::transaction(function () use ($source) {
            $copy = AccessProfile::create(['name' => AccessProfile::uniqueName("Cópia de {$source->name}")]);

            foreach ($source->modules()->pluck('module') as $module) {
                $copy->modules()->create(['module' => $module]);
            }
            $copy->boards()->attach($source->boards()->withTrashed()->pluck('boards.id')->all());

            return $copy;
        });
    }
}
