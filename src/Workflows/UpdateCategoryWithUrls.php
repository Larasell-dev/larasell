<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Catalog\CategoryId;
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
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(CategoryId $categoryId, UpdateCategoryPayload $payload, array $urls): Category
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($categoryId, $payload, $plan): Category {
            $category = $this->update->handle($categoryId, $payload);
            $this->urls->apply($category, $plan);

            return $category;
        });
    }
}
