<?php

declare(strict_types=1);

use App\AuditRecords\Products\ProductCreated;
use Illuminate\Support\Facades\File;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;

afterEach(function () {
    File::deleteDirectory(app_path('AuditRecords'));
});

it('scaffolds an audit record in the AuditRecords namespace', function () {
    // Act
    $this->artisan('make:larasell-audit-record', ['name' => 'Products/ProductCreated'])->assertSuccessful();

    // Assert
    $path = app_path('AuditRecords/Products/ProductCreated.php');

    expect(File::get($path))
        ->toContain('namespace App\AuditRecords\Products;')
        ->toContain('class ProductCreated extends AuditRecord');

    // The scaffold is a working record as it is, so it can be filled in step by step.
    require $path;
    $record = new ProductCreated;

    expect($record)->toBeInstanceOf(AuditRecord::class)
        ->and($record->event())->toBe('product.created')
        ->and($record->category())->toBe(AuditCategory::BusinessCritical)
        ->and($record->subject())->toBeNull()
        ->and($record->properties())->toBe([]);
});

it('names the event after what happened to what', function (string $name, string $event) {
    // Act
    $this->artisan('make:larasell-audit-record', ['name' => $name])->assertSuccessful();

    // Assert
    expect(File::get(app_path('AuditRecords/'.$name.'.php')))->toContain("return '{$event}';");
})->with([
    'a two-word subject' => ['ProductReviewApproved', 'product_review.approved'],
    'a single word' => ['Imported', 'imported'],
]);

it('keeps an existing record unless forced', function () {
    // Arrange
    $this->artisan('make:larasell-audit-record', ['name' => 'OrderRefunded'])->assertSuccessful();
    File::put(app_path('AuditRecords/OrderRefunded.php'), '<?php // edited');

    // Act & Assert
    // Like Laravel's own make commands: it says so rather than failing.
    $this->artisan('make:larasell-audit-record', ['name' => 'OrderRefunded'])->expectsOutputToContain('already exists');
    expect(File::get(app_path('AuditRecords/OrderRefunded.php')))->toBe('<?php // edited');

    $this->artisan('make:larasell-audit-record', ['name' => 'OrderRefunded', '--force' => true])->assertSuccessful();
    expect(File::get(app_path('AuditRecords/OrderRefunded.php')))->toContain('class OrderRefunded extends AuditRecord');
});
