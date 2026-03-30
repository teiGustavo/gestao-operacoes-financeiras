<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows welcome page with login link for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Bem-vindo(a) ao sistema')
        ->assertSee('Entrar')
        ->assertDontSee('Sair');
});

it('shows welcome page with logout action for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Esteira')
        ->assertSee('Sair')
        ->assertDontSee('Entrar')
        ->assertSee('action="'.route('logout').'"', false);
});

it('renders the login page for guests', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('Entrar')
        ->assertSee('Usuário administrador (desenvolvimento)')
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

    $this->post('/login', [
        'login' => $user->username,
        'password' => 'secret123',
    ])->assertRedirect('/operations/1');

    $this->assertAuthenticatedAs($user);
});

it('authenticates with seeded administrator credentials', function () {
    $this->seed(AdminUserSeeder::class);

    $this->post('/login', [
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

    $this->post('/login', [
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

    $this->from('/login')->post('/login', [
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

    $this->patch('/operations/999/status', [
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
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('shows a success message after logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->post('/logout')
        ->assertSee('Sessao encerrada com sucesso.');
});
