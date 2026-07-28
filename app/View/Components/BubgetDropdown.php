<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BubgetDropdown extends Component
{
    public $budget;
    public function __construct($budget)
    {
        $this->budget = $budget;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.bubget-dropdown');
    }
}
