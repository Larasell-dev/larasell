<?php

namespace Larasell\Larasell\Admin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Larasell\Larasell\Admin\Models\AdminUser;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create-user
        {--name= : The admin user name}
        {--email= : The admin user email address}
        {--password= : The admin user password}';

    protected $description = 'Create a Larasell admin panel user.';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Name',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
            validate: ['email' => ['required', 'email']],
        );

        $plainPassword = $this->option('password') ?: password(
            label: 'Password',
            required: true,
            validate: ['password' => ['required', Password::defaults()]],
        );

        validator([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:larasell_admin_users,email'],
            'password' => ['required', Password::defaults()],
        ])->validate();

        if (! is_string($name) || ! is_string($email) || ! is_string($plainPassword)) {
            throw new \InvalidArgumentException('Admin user credentials must be strings.');
        }

        $model = config('larasell-admin.models.admin_user', AdminUser::class);

        $model::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
        ]);

        $this->components->info('Larasell admin user created.');

        return self::SUCCESS;
    }
}
