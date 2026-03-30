<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows welcome page with login link for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertViewIs('home')
        ->assertViewHas('page', static function (array $page): bool {
            return array_key_exists('title', $page)
                && array_key_exists('welcome_heading', $page)
                && array_key_exists('welcome_description', $page);
        })
        ->assertSee('Bem-vindo(a) ao sistema')
        ->assertSee('Entrar')
        ->assertDontSee('Sair');
});

it('shows welcome page with logout action for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertViewIs('home')
        ->assertViewHas('page')
        ->assertSee('Esteira')
        ->assertSee('Sair')
        ->assertDontSee('Entrar')
        ->assertSee('action="'.route('logout').'"', false);
});

it('renders the login page for guests', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertViewIs('auth.login')
        ->assertViewHas('page', static function (array $page): bool {
            return array_key_exists('title', $page)
                && array_key_exists('heading', $page)
                && array_key_exists('subtitle', $page);
        })
        ->assertViewHas('demoCredentials', static function (array $demoCredentials): bool {
            return array_key_exists('username', $demoCredentials)
                && array_key_exists('email', $demoCredentials)
                && array_key_exists('password', $demoCredentials)
                && array_key_exists('shouldDisplay', $demoCredentials);
        })
        ->assertSee('Entrar')
        ->assertSee('administrador (desenvolvimento)')
        ->assertSee('admin@finance.local')
        ->assertSee('admin123')
        ->assertSee('value="admin@finance.local"', false)
        ->assertSee('value="admin123"', false);
});

it('seeds an administrator user', function () {
    $this->seed(AdminUserSeeder::class);

    $this->assertDatabaseHas('users', [
        'name' => 'Administrador',
        'email' => 'admin@finance.local',
        'username' => 'admin',
    ]);
});

it('authenticates with username and redirects to intended url', function () {
    $user = User::factory()->create([
        'username' => 'joao.silva',
        'password' => 'secret123',
    ]);

    $this->get('/operations/1');

    $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
        '_token' => 'test-csrf-token',
        'login' => $user->username,
        'password' => 'secret123',
    ])->assertRedirect('/operations/1');

    $this->assertAuthenticatedAs($user);
});

it('authenticates with seeded administrator credentials', function () {
    $this->seed(AdminUserSeeder::class);

    $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
        '_token' => 'test-csrf-token',
        'login' => 'admin@finance.local',
        'password' => 'admin123',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
});

it('authenticates with email', function () {
    $user = User::factory()->create([
        'email' => 'joao@example.com',
        'password' => 'secret123',
    ]);

    $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
        '_token' => 'test-csrf-token',
        'login' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('does not authenticate with invalid credentials', function () {
    User::factory()->create([
        'email' => 'joao@example.com',
        'password' => 'secret123',
    ]);

    $this->from('/login')
        ->withSession(['_token' => 'test-csrf-token'])
        ->post('/login', [
            '_token' => 'test-csrf-token',
            'login' => 'joao@example.com',
            'password' => 'senha-incorreta',
        ])->assertRedirect('/login')
        ->assertSessionHasErrors([
            'login' => 'Credenciais invalidas. Verifique login e senha.',
        ]);

    $this->assertGuest();
});

it('redirects guests when trying to access protected system routes', function () {
    $this->get('/operations/999')->assertRedirect('/login');

    $this->withSession(['_token' => 'test-csrf-token'])->patch('/operations/999/status', [
        '_token' => 'test-csrf-token',
        'status' => 'approved',
    ])->assertRedirect('/login');
});

it('redirects authenticated users away from login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/');
});

it('logs out authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->post('/logout', [
            '_token' => 'test-csrf-token',
        ])
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('shows a success message after logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->followingRedirects()
        ->post('/logout', [
            '_token' => 'test-csrf-token',
        ])
        ->assertSee('Sessao encerrada com sucesso.');
});
