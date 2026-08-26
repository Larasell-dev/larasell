<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $path
 * @property string|null $alt
 * @property array<string, mixed>|null $meta
 */
class ProductImage extends Model
{
    use HasFactory;

    protected $table = 'larasell_product_images';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

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

    protected function productModel(): string
    {
        return app(ModelRegistry::class)->product->class();
    }
}
