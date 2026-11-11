<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Facades\Audit;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Actions\RestoreOldUrl;
use Larasell\Larasell\Seo\AuditRecords\SeoUrlReleased as ReleasedRecord;
use Larasell\Larasell\Seo\AuditRecords\SeoUrlRestored as RestoredRecord;
use Larasell\Larasell\Seo\Events\SeoUrlRestored;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoUrlId;
use Larasell\Larasell\Seo\SeoUrlManager;
use Larasell\Larasell\Seo\SeoUrlStatus;

beforeEach(function () {
    $this->origin = Origin::web(new SystemActor('back-office'));
    $this->boots = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000), 'status' => 'visible']), $this->origin);
    app(UpdateProduct::class)->handle(ProductId::of($this->boots), new UpdateProductPayload(['name' => ['en' => 'Hiking boots']]), $this->origin);
});

function oldBootsUrl(Product $boots): SeoUrl
{
    return SeoUrl::query()->where('resource_id', $boots->id)->where('status', SeoUrlStatus::Historic)->sole();
}

function currentPath(Product $boots): ?string
{
    return app(SeoUrlManager::class)->canonical($boots, SalesContext::for('default', 'en'))?->path;
}

it('makes an old URL the page\'s current one again, and audits it with the caller\'s origin', function () {
    // Arrange
    Audit::fake();
    $old = oldBootsUrl($this->boots);
    Event::fake([SeoUrlRestored::class]);

    // Act
    app(RestoreOldUrl::class)->handle(SeoUrlId::of($old), $this->origin);

    // Assert
    expect(currentPath($this->boots))->toBe($old->path);
    Event::assertDispatched(SeoUrlRestored::class, fn (SeoUrlRestored $event) => $event->origin === $this->origin && $event->path === $old->path);
});

it('records the restore in the audit log', function () {
    // Arrange
    Audit::fake();
    $old = oldBootsUrl($this->boots);

    // Act
    app(RestoreOldUrl::class)->handle(SeoUrlId::of($old), $this->origin);

    // Assert
    Audit::assertRecorded(RestoredRecord::class, fn (RestoredRecord $record, Origin $origin) => $origin === $this->origin
        && $record->subject()->type === 'product' && $record->subject()->id === (string) $this->boots->id
        && $record->properties()['path'] === $old->path);
});

it('refuses a URL that isn\'t an old one', function () {
    // Arrange
    $current = SeoUrl::query()->where('resource_id', $this->boots->id)->where('status', SeoUrlStatus::Canonical)->sole();

    // Act & Assert
    expect(fn () => app(RestoreOldUrl::class)->handle(SeoUrlId::of($current), $this->origin))->toThrow(ModelNotFoundException::class);
});

it('audits a released URL with the origin of the change that released it', function () {
    // Arrange
    Audit::fake();
    $old = oldBootsUrl($this->boots);

    // Act
    app(SeoUrlManager::class)->remove($old, $this->origin);

    // Assert
    Audit::assertRecorded(ReleasedRecord::class, fn (ReleasedRecord $record, Origin $origin) => $origin === $this->origin
        && $record->properties()['path'] === $old->path);
});
