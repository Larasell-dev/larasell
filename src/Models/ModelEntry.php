<?php

namespace Larasell\Larasell\Models;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
final readonly class ModelEntry
{
    /**
     * @param class-string<TModel> $default
     */
    public function __construct(
        private Repository $config,
        private string $key,
        private string $default,
    ) {}

    /** @return class-string<TModel> */
    public function class(): string
    {
        return $this->config->get($this->key, $this->default);
    }

    /** @return TModel */
    public function new(): Model
    {
        $class = $this->class();

        return new $class;
    }

    /** @return Builder<TModel> */
    public function query(): Builder
    {
        return $this->new()->newQuery();
    }
}
