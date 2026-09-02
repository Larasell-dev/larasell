<?php

namespace Larasell\Larasell\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

class InstallStarterKitCommand extends Command
{
    protected $signature = 'larasell:install
        {--force : Overwrite existing starter kit files}';

    protected $description = 'Install the Larasell starter kit.';

    public function handle(Filesystem $files): int
    {
        $source = dirname(__DIR__, 2).'/stubs/starter-kit';
        $sourcePrefixLength = strlen($source) + 1;
        $installations = [];

        foreach ($files->allFiles($source) as $file) {
            $relativePath = substr($file->getPathname(), $sourcePrefixLength);
            $installations[$file->getPathname()] = base_path($relativePath);
        }

        $conflicts = array_filter(
            $installations,
            fn (string $destination): bool => $files->exists($destination),
        );

        if ($conflicts !== [] && ! $this->option('force')) {
            $this->components->error('Starter kit files already exist. Use --force to overwrite them.');

            foreach ($conflicts as $destination) {
                $this->line("  {$destination}");
            }

            return self::FAILURE;
        }

        if (! $this->installDependencies($files)) {
            return self::FAILURE;
        }

        foreach ($installations as $sourceFile => $destination) {
            $files->ensureDirectoryExists(dirname($destination));
            $files->copy($sourceFile, $destination);
        }

        $this->registerOrderConfirmationRoute($files);

        $this->components->info('Larasell starter kit installed.');

        return self::SUCCESS;
    }

    private function installDependencies(Filesystem $files): bool
    {
        if ($files->exists(base_path('composer.json'))) {
            $this->components->info('Installing Inertia...');

            $result = $this->runDependencyCommand([
                'composer',
                'require',
                'inertiajs/inertia-laravel:^3.0',
                '--no-interaction',
            ]);

            if (! $result->successful()) {
                return $this->dependencyInstallationFailed($result);
            }
        }

        if ($files->exists(base_path('package.json'))) {
            $this->components->info('Installing React and Inertia...');

            $result = $this->runDependencyCommand([
                'npm',
                'install',
                '@inertiajs/react',
                'react',
                'react-dom',
            ]);

            if (! $result->successful()) {
                return $this->dependencyInstallationFailed($result);
            }

            $result = $this->runDependencyCommand([
                'npm',
                'install',
                '--save-dev',
                '@types/react',
                '@types/react-dom',
                '@vitejs/plugin-react',
                'typescript',
            ]);

            if (! $result->successful()) {
                return $this->dependencyInstallationFailed($result);
            }
        }

        return true;
    }

    /** @param array<int, string> $command */
    private function runDependencyCommand(array $command): ProcessResult
    {
        return Process::path(base_path())
            ->timeout(300)
            ->run($command);
    }

    private function dependencyInstallationFailed(ProcessResult $result): bool
    {
        $this->components->error('Unable to install the starter kit dependencies.');

        $output = trim($result->errorOutput() ?: $result->output());

        if ($output !== '') {
            $this->line($output);
        }

        return false;
    }

    private function registerOrderConfirmationRoute(Filesystem $files): void
    {
        $routesPath = base_path('routes/web.php');

        if (! $files->exists($routesPath)) {
            return;
        }

        $routes = $files->get($routesPath);

        if (str_contains($routes, "name('orders.confirmation')")) {
            return;
        }

        $route = <<<'PHP'

\Illuminate\Support\Facades\Route::get(
    '/orders/{publicId}/confirmation',
    [\App\Http\Controllers\OrderController::class, 'show'],
)->name('orders.confirmation');
PHP;

        $files->append($routesPath, $route.PHP_EOL);
    }
}
