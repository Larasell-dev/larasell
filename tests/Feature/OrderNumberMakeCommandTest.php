<?php

use Illuminate\Support\Facades\File;

it('creates an order number generator in the generators directory', function () {
    $path = app_path('OrderNumbers/Generators/CustomOrderNumberGenerator.php');

    File::deleteDirectory(app_path('OrderNumbers'));

    expect(File::exists($path))->toBeFalse();

    $this->artisan('make:order-number-generator', ['name' => 'CustomOrderNumberGenerator'])
        ->assertSuccessful();

    expect(File::exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe(<<<'CLASS'
        <?php

        namespace App\OrderNumbers\Generators;

        use Larasell\Larasell\Contracts\OrderNumberGenerator;
        use Larasell\Larasell\OrderNumbers\SingleOrderNumberSequence;

        class CustomOrderNumberGenerator implements OrderNumberGenerator
        {
            public function __construct(private readonly SingleOrderNumberSequence $sequence) {}

            public function generate(): string
            {
                $next = $this->sequence->next();

                return 'ORD-'.$next;
            }
        }

        CLASS);

    File::deleteDirectory(app_path('OrderNumbers'));
});

it('does not overwrite an existing order number generator', function () {
    $path = app_path('OrderNumbers/Generators/CustomOrderNumberGenerator.php');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php // existing');

    $this->artisan('make:order-number-generator', ['name' => 'CustomOrderNumberGenerator'])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();

    expect(file_get_contents($path))->toBe('<?php // existing');

    File::deleteDirectory(app_path('OrderNumbers'));
});

it('creates an order number sequence in the sequences directory', function () {
    $path = app_path('OrderNumbers/Sequences/RedisOrderNumberSequence.php');

    File::deleteDirectory(app_path('OrderNumbers'));

    expect(File::exists($path))->toBeFalse();

    $this->artisan('make:order-number-sequence', ['name' => 'RedisOrderNumberSequence'])
        ->assertSuccessful();

    expect(File::exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe(<<<'CLASS'
        <?php

        namespace App\OrderNumbers\Sequences;

        use Larasell\Larasell\Contracts\OrderNumberSequence;

        class RedisOrderNumberSequence implements OrderNumberSequence
        {
            public function next(string $name): int
            {
                //
            }
        }

        CLASS);

    File::deleteDirectory(app_path('OrderNumbers'));
});

it('does not overwrite an existing order number sequence', function () {
    $path = app_path('OrderNumbers/Sequences/RedisOrderNumberSequence.php');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php // existing');

    $this->artisan('make:order-number-sequence', ['name' => 'RedisOrderNumberSequence'])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();

    expect(file_get_contents($path))->toBe('<?php // existing');

    File::deleteDirectory(app_path('OrderNumbers'));
});
