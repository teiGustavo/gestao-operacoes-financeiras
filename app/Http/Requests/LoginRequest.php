<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const int MAX_LOGIN_ATTEMPTS = 5;

    private const string INVALID_CREDENTIALS_MESSAGE = 'Credenciais invalidas. Verifique login e senha.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), (bool) $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => self::INVALID_CREDENTIALS_MESSAGE,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $login = (string) $this->string('login');
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $loginField => $login,
            'password' => (string) $this->string('password'),
        ];
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => 'Muitas tentativas de login. Tente novamente em '.$seconds.' segundos.',
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('login')).'|'.$this->ip());
    }
}
