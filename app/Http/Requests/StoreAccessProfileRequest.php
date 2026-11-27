<?php

namespace App\Http\Requests;

use App\Models\AccessProfile;

class StoreAccessProfileRequest extends AccessProfileRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AccessProfile::class);
    }
}
