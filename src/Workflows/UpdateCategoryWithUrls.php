<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\Models\Category;

/** Updates a category and its custom URLs: both are saved, or neither, e.g. when a path is taken. */
final readonly class UpdateCategoryWithUrls
{
    public function __construct(
        private ConnectionInterface $db,
        private UpdateCategory $update,
        private CustomUrls $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  As for UpdateCategory
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(int $categoryId, array $attributes, array $urls): Category
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($categoryId, $attributes, $plan): Category {
            $category = $this->update->handle($categoryId, $attributes);
            $this->urls->apply($category, $plan);

            return $category;
        });
    }
}
