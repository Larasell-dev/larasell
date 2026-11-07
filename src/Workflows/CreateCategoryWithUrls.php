<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
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
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(CreateCategoryPayload $payload, array $urls): Category
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($payload, $plan): Category {
            $category = $this->create->handle($payload);
            $this->urls->apply($category, $plan);

            return $category;
        });
    }
}
