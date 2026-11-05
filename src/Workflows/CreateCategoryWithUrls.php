<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Models\Category;

/** Creates a category with custom URLs: both are saved, or neither, e.g. when a path is taken. */
final readonly class CreateCategoryWithUrls
{
    public function __construct(
        private ConnectionInterface $db,
        private CreateCategory $create,
        private CustomUrls $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  As for CreateCategory
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(array $attributes, array $urls): Category
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($attributes, $plan): Category {
            $category = $this->create->handle($attributes);
            $this->urls->apply($category, $plan);

            return $category;
        });
    }
}
