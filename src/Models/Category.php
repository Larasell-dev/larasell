<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Relations\Siblings;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property Translatable $name
 * @property Visibility $status
 * @property int $position
 *
 * @method static Builder<static> root()
 */
class Category extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_categories';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'visible',
    ];

    protected $casts = [
        'name' => TranslatableCast::class,
        'status' => Visibility::class,
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        // A new category goes after its siblings, unless it is given a position.
        static::creating(function (self $category): void {
            if ($category->getAttribute('position') !== null) {
                return;
            }

            $last = $category->newQuery()
                ->where('parent_id', $category->parent_id)
                ->max('position');

            $category->position = $last === null ? 0 : (int) $last + 1;
        });
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'parent_id'
        );
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->ordered($this->hasMany(
            Category::class,
            'parent_id'
        ));
    }

    /**
     * @return HasMany<self, $this>
     */
    public function descendants(): HasMany
    {
        return $this->onlyVisible($this->children())->with('descendants');
    }

    /**
     * @return Siblings<self>
     */
    public function siblings(): Siblings
    {
        return $this->ordered($this->onlyVisible(
            $this->newSiblingsRelation()
        ));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function root(Builder $query): Builder
    {
        return $this->ordered($this->onlyVisible($query)->whereNull('parent_id'));
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'larasell_category_product',
            'category_id',
            'product_id'
        )->withTimestamps();
    }

    /** @return Siblings<Category> */
    protected function newSiblingsRelation(): Siblings
    {
        return new Siblings(Category::query(), $this);
    }

    /**
     * @template TQuery of Builder<self>|HasMany<self, $this>|Siblings<self>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function ordered(Builder|HasMany|Siblings $query): Builder|HasMany|Siblings
    {
        $query->orderBy('position')->orderBy('id');

        return $query;
    }

    /**
     * @template TQuery of Builder<self>|HasMany<self, $this>|Siblings<self>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function onlyVisible(Builder|HasMany|Siblings $query): Builder|HasMany|Siblings
    {
        return $query->where('status', Visibility::Visible->value);
    }
}
