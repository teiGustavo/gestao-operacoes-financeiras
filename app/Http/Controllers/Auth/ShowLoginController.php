<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ShowLoginController extends Controller
{
    private const string ADMIN_USERNAME = 'admin';

    private const string ADMIN_EMAIL = 'admin@finance.local';

    private const string ADMIN_PASSWORD = 'admin123';

    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        $showDemoCredentials = app()->environment(['local', 'testing']);

        return view('auth.login', [
            'demoCredentials' => [
                'username' => old('username', $showDemoCredentials ? self::ADMIN_USERNAME : ''),
                'email' => old('login', $showDemoCredentials ? self::ADMIN_EMAIL : ''),
                'password' => $showDemoCredentials ? self::ADMIN_PASSWORD : '',
                'shouldDisplay' => $showDemoCredentials,
            ],
        ]);
    }
}
