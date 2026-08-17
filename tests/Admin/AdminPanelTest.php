<?php

use Illuminate\Support\Facades\Hash;
use Larasell\Larasell\Admin\Models\AdminUser;

it('registers the admin login route when the admin provider is registered', function () {
    expect(route('larasell.admin.login'))->toContain('/admin/login');
});

it('redirects guest admin users to the larasell admin login route', function () {
    $this->get('/admin')->assertRedirect(route('larasell.admin.login'));
});

it('creates an admin user from the console command', function () {
    $this->artisan('admin:create-user', [
        '--name' => 'Larasell Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password',
    ])->assertSuccessful();

    $admin = AdminUser::query()->where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Larasell Admin')
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});
