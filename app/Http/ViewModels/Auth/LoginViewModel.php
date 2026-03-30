<?php

declare(strict_types=1);

namespace App\Http\ViewModels\Auth;

final class LoginViewModel
{
    private const string ADMIN_USERNAME = 'admin';

    private const string ADMIN_EMAIL = 'admin@finance.local';

    private const string ADMIN_PASSWORD = 'admin123';

    /**
     * @return array{
     *     page: array{title: string, heading: string, subtitle: string},
     *     demoCredentials: array{username: string, email: string, password: string, shouldDisplay: bool}
     * }
     */
    public function toArray(): array
    {
        $showDemoCredentials = app()->environment(['local', 'testing']);

        return [
            'page' => [
                'title' => 'Login',
                'heading' => 'Entrar',
                'subtitle' => 'Acesse com email/nome de usuário e senha.',
            ],
            'demoCredentials' => [
                'username' => old('username', $showDemoCredentials ? self::ADMIN_USERNAME : ''),
                'email' => old('login', $showDemoCredentials ? self::ADMIN_EMAIL : ''),
                'password' => $showDemoCredentials ? self::ADMIN_PASSWORD : '',
                'shouldDisplay' => $showDemoCredentials,
            ],
        ];
    }
}
