<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout de captura a pantalla completa (estilo Siigo):
 * sin sidebar ni navbar de tienda, para formularios de documentos.
 */
class CaptureLayout extends Component
{
    public function render(): View
    {
        return view('layouts.capture');
    }
}
