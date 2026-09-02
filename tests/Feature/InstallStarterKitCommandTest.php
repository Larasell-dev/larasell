<?php

use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->originalBasePath = $this->app->basePath();
    $this->starterKitBasePath = sys_get_temp_dir().'/larasell-starter-kit-'.bin2hex(random_bytes(8));
    $this->app->setBasePath($this->starterKitBasePath);
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);
    (new Filesystem)->deleteDirectory($this->starterKitBasePath);
});

it('installs the starter kit files', function () {
    $destination = $this->starterKitBasePath.'/app/Http/Controllers/LarasellStarterController.php';

    $this->artisan('larasell:install')
        ->expectsOutputToContain('Larasell starter kit installed.')
        ->assertSuccessful();

    expect($destination)->toBeFile()
        ->and(file_get_contents($destination))->toContain('Hello from the Larasell starter kit.');
});

it('does not overwrite starter kit files without force', function () {
    $destination = $this->starterKitBasePath.'/app/Http/Controllers/LarasellStarterController.php';
    (new Filesystem)->ensureDirectoryExists(dirname($destination));
    file_put_contents($destination, 'existing application code');

    $this->artisan('larasell:install')
        ->expectsOutputToContain('Starter kit files already exist.')
        ->assertFailed();

    expect(file_get_contents($destination))->toBe('existing application code');
});

it('overwrites starter kit files with force', function () {
    $destination = $this->starterKitBasePath.'/app/Http/Controllers/LarasellStarterController.php';
    (new Filesystem)->ensureDirectoryExists(dirname($destination));
    file_put_contents($destination, 'existing application code');

    $this->artisan('larasell:install', ['--force' => true])
        ->assertSuccessful();

    expect(file_get_contents($destination))->toContain('Hello from the Larasell starter kit.');
});
