<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    /**
     * Layout público (formulário externo), sem autenticação.
     */
    public function render(): View
    {
        return view('layouts.public');
    }
}
