<?php

namespace Larasell\Larasell\Catalog\Options\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionDeleted;
use Larasell\Larasell\Catalog\Options\Exceptions\OptionInUse;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;
use Larasell\Larasell\Catalog\Options\OptionUsage;

/**
 * Deletes an option with its values while no variant uses it; products that only offer it stop
 * offering it. Otherwise OptionInUse says how many variants on how many products use it.
 */
final readonly class DeleteOptionDefinition
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(OptionDefinitionId $definitionId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $definitionId): void {
            $definition = OptionDefinition::query()->lockForUpdate()->findOrFail($definitionId->value);
            $valueIds = $definition->values()->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
            $usage = OptionUsage::ofValues($valueIds);

            if ($usage['variants'] > 0) {
                throw OptionInUse::option($definition->slug, $usage['variants'], $usage['products']);
            }

            OptionUsage::withdraw($valueIds);
            $definition->values()->delete();
            $definition->delete();

            OptionDefinitionDeleted::dispatch($definitionId, array_values(array_map(fn (int $id): OptionDefinitionValueId => new OptionDefinitionValueId($id), $valueIds)), origin: $origin);
        });
    }
}
