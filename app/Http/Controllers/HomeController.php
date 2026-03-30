<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ViewModels\HomeViewModel;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly HomeViewModel $homeViewModel) {}

    public function __invoke(): View
    {
        return view('home', $this->homeViewModel->toArray());
    }
}
