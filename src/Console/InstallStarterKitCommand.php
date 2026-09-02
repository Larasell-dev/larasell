<?php

namespace Larasell\Larasell\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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

        foreach ($installations as $sourceFile => $destination) {
            $files->ensureDirectoryExists(dirname($destination));
            $files->copy($sourceFile, $destination);
        }

        $this->components->info('Larasell starter kit installed.');

        return self::SUCCESS;
    }
}
