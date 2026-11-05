<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Models\Category;

final readonly class UpdateCategory
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  Anything but its place in the tree; see MoveCategory */
    public function handle(int $categoryId, array $attributes): Category
    {
        if (array_key_exists('parent_id', $attributes) || array_key_exists('position', $attributes)) {
            throw new InvalidArgumentException('Move a category with '.MoveCategory::class.', which keeps the tree free of cycles.');
        }

        return $this->db->transaction(function () use ($categoryId, $attributes): Category {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId);
            $changed = array_keys($category->fill($attributes)->getDirty());

            if ($changed !== []) {
                $category->save();
                CategoryUpdated::dispatch($category->id, $changed);
            }

            return $category;
        });
    }
}
