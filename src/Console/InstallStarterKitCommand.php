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

        $replaceable = [
            base_path('routes/web.php'),
            base_path('vite.config.js'),
        ];
        $conflicts = array_filter(
            $installations,
            fn (string $destination): bool => $files->exists($destination)
                && ! in_array($destination, $replaceable, true),
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

        if (! $this->registerInertiaMiddleware($files)) {
            return self::FAILURE;
        }

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
                '@larasell-dev/inertia-i18n',
                '@inertiajs/react',
                '@inertiajs/vite',
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
                '@types/node',
                '@vitejs/plugin-react',
                'typescript',
            ]);

            if (! $result->successful()) {
                return $this->dependencyInstallationFailed($result);
            }

            if (! $this->configureSsrBuildScript($files)) {
                return false;
            }
        }

        return true;
    }

    private function configureSsrBuildScript(Filesystem $files): bool
    {
        $packagePath = base_path('package.json');
        $package = json_decode($files->get($packagePath), true);

        if (! is_array($package)) {
            $this->components->error('Unable to configure SSR in package.json.');

            return false;
        }

        $package['scripts'] = is_array($package['scripts'] ?? null) ? $package['scripts'] : [];
        $package['scripts']['build'] = 'vite build && vite build --ssr';

        $files->put(
            $packagePath,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

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

    private function registerInertiaMiddleware(Filesystem $files): bool
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (! $files->exists($bootstrapPath)) {
            return true;
        }

        $bootstrap = $files->get($bootstrapPath);

        if (str_contains($bootstrap, 'HandleInertiaRequests::class')) {
            return true;
        }

        $middlewareImport = "use Illuminate\\Foundation\\Configuration\\Middleware;\n";
        $middlewareCallback = "->withMiddleware(function (Middleware \$middleware): void {\n";

        if (! str_contains($bootstrap, $middlewareImport) || ! str_contains($bootstrap, $middlewareCallback)) {
            $this->components->error('Unable to register the Inertia middleware in bootstrap/app.php.');

            return false;
        }

        $bootstrap = str_replace(
            $middlewareImport,
            "use App\\Http\\Middleware\\HandleInertiaRequests;\n{$middlewareImport}",
            $bootstrap,
        );
        $bootstrap = str_replace(
            $middlewareCallback,
            $middlewareCallback."        \$middleware->web(append: [HandleInertiaRequests::class]);\n",
            $bootstrap,
        );

        $files->put($bootstrapPath, $bootstrap);

        return true;
    }
}
