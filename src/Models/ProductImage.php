<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Casts\PlaceholderCast;
use Larasell\Larasell\Contracts\PlaceholderGenerator;
use Larasell\Larasell\Images\Placeholder;

/**
 * @property int $id
 * @property string $path
 * @property string|null $alt
 * @property Placeholder|null $placeholder
 * @property array<string, mixed>|null $meta
 */
class ProductImage extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_product_images';

    protected $guarded = [];

    protected $casts = [
        'placeholder' => PlaceholderCast::class,
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductImage $image): void {
            if ($image->isSvg()) {
                $image->placeholder = null;

                return;
            }

            if ($image->exists && ! $image->isDirty('path')) {
                return;
            }

            if (! $image->exists && $image->isDirty('placeholder')) {
                return;
            }

            $image->refreshPlaceholder();
        });
    }

    public function refreshPlaceholder(): void
    {
        $this->placeholder = $this->isSvg()
            ? null
            : app(PlaceholderGenerator::class)->generate($this);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productModel(),
            'larasell_product_product_image',
            'product_image_id',
            'product_id'
        )->withPivot('position')->withTimestamps();
    }

    public function url(): string
    {
        return Storage::disk(config('larasell.images.disk'))->url($this->path);
    }

    /**
     * Vector originals cannot be decoded into raster placeholders or conversions.
     */
    public function isSvg(): bool
    {
        $meta = $this->getAttribute('meta');
        $candidates = [
            $this->getAttribute('path'),
            is_array($meta) ? ($meta['original_name'] ?? null) : null,
            is_array($meta) ? ($meta['mime_type'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = strtolower($candidate);

            if (str_contains($normalized, 'image/svg') || str_ends_with($normalized, '.svg') || str_ends_with($normalized, '.svgz')) {
                return true;
            }
        }

        return false;
    }

    /** @return class-string<Product> */
    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }
}
