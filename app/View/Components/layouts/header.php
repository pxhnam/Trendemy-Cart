<?php

namespace App\View\Components\layouts;

use App\Services\Interfaces\CartServiceInterface;
use Closure;
use Illuminate\View\Component;
use Illuminate\Contracts\View\View;

class header extends Component
{
    public function __construct(
        public CartServiceInterface $cartService,
        public int $countCart = 0
    ) {
        $this->countCart = $this->cartService->count();
    }

    public function render(): View|Closure|string
    {
        return view('components.layouts.header');
    }
}
