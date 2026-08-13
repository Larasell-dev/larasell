<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Larasell\Larasell\Enums\Visibility;

class Product extends Model
{
    use HasFactory;

    protected $table = 'larasell_products';

    protected $guarded = [];

    protected $casts = [
        'status' => Visibility::class,
    ];

    /**
     * @param Builder<self> $query
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('status', Visibility::Visible->value);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->categoryModel(),
            'larasell_category_product',
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    protected function categoryModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.category', Category::class)
            : Category::class;
    }
}
