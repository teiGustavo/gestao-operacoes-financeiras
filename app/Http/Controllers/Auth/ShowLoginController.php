<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\ViewModels\Auth\LoginViewModel;
use Illuminate\Contracts\View\View;

final class ShowLoginController extends Controller
{
    public function __construct(private readonly LoginViewModel $loginViewModel) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        return view('auth.login', $this->loginViewModel->toArray());
    }
}
