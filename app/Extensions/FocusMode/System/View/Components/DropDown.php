<?php

namespace App\Extensions\FocusMode\System\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DropDown extends Component
{
    public function __construct() {}

    public function render(): View
    {
        return view('focus-mode::dropdown');
    }
}
