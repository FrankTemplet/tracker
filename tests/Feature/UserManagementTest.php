<?php

use App\Models\User;
use App\Services\PowerBiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('users.index'));
    $response->assertRedirect(route('login'));
});

test('viewers cannot access user management', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('users.index'))->assertForbidden();
    $this->post(route('users.store'))->assertForbidden();
    $this->patch(route('users.update', User::factory()->create()))->assertForbidden();
});

test('admins can view the user management page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $this->actingAs($admin);

    $response = $this->get(route('users.index'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users')
            ->has('users', 3)
            ->where('regions', ['carib', 'networks'])
            ->where('roles', ['admin', 'viewer'])
        );
});

test('admins can create users', function () {
    $this->actingAs(User::factory()->admin()->create());

    $response = $this->post(route('users.store'), [
        'name' => 'New Viewer',
        'email' => 'viewer@templet.io',
        'password' => 'Templet2026+',
        'region' => 'networks',
        'role' => 'viewer',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'viewer@templet.io',
        'region' => 'networks',
        'role' => 'viewer',
    ]);
});

test('user creation validates region and role', function () {
    $this->actingAs(User::factory()->admin()->create());

    $response = $this->post(route('users.store'), [
        'name' => 'New Viewer',
        'email' => 'viewer@templet.io',
        'password' => 'Templet2026+',
        'region' => 'latam',
        'role' => 'superadmin',
    ]);

    $response->assertSessionHasErrors(['region', 'role']);
});

test('admins can change a user region', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = $this->patch(route('users.update', $user), ['region' => 'networks']);

    $response->assertRedirect(route('users.index'));
    expect($user->refresh()->region)->toBe('networks');
});

test('admins can change a user role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);

    $this->patch(route('users.update', $user), ['role' => 'admin']);

    expect($user->refresh()->role)->toBe('admin');
});

test('admins cannot remove their own admin role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $response = $this->patch(route('users.update', $admin), ['role' => 'viewer']);

    $response->assertSessionHasErrors('role');
    expect($admin->refresh()->role)->toBe('admin');
});

test('admins see every region on the dashboard', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(false);

    $this->actingAs(User::factory()->admin()->create());

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('availableRegions', ['carib', 'networks', 'latam'])
        );
});

test('allowed regions depend on role and region', function () {
    expect(User::factory()->create()->allowedRegions())->toBe(['carib'])
        ->and(User::factory()->networks()->create()->allowedRegions())->toBe(['networks', 'latam'])
        ->and(User::factory()->admin()->create()->allowedRegions())->toBe(['carib', 'networks', 'latam'])
        ->and(User::factory()->admin()->networks()->create()->allowedRegions())->toBe(['carib', 'networks', 'latam']);
});
