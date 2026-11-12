<?php

namespace Larasell\Larasell\Catalog\Options\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionUpdated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueDeleted;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueUpdated;
use Larasell\Larasell\Catalog\Options\Exceptions\InvalidOptionValues;
use Larasell\Larasell\Catalog\Options\Exceptions\OptionInUse;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\Options\OptionDefinitionId;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;
use Larasell\Larasell\Catalog\Options\OptionUsage;

/**
 * Creates an option, or updates one, and leaves it with exactly the given values: drafts without
 * an id are created, the others updated, and values left out deleted. A value variants use isn't
 * deleted: OptionInUse says how many variants on how many products use it, and those variants go
 * first, on their products. Products that only offer a deleted value stop offering it. Positions
 * follow the drafts' order. Each change dispatches its event, all in one transaction.
 */
final readonly class SaveOptionDefinition
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(?OptionDefinitionId $definitionId, SaveOptionDefinitionPayload $payload, Origin $origin): OptionDefinition
    {
        return $this->db->transaction(function () use ($origin, $definitionId, $payload): OptionDefinition {
            $fields = ['name' => $payload->name, 'slug' => $payload->slug, 'display' => $payload->display->value];

            if ($definitionId === null) {
                $definition = OptionDefinition::query()->create($fields);
                OptionDefinitionCreated::dispatch(OptionDefinitionId::of($definition), origin: $origin);
            } else {
                $definition = OptionDefinition::query()->lockForUpdate()->findOrFail($definitionId->value);
                $changed = array_keys($definition->fill($fields)->getDirty());
                $definition->save();

                if ($changed !== []) {
                    OptionDefinitionUpdated::dispatch($definitionId, $changed, origin: $origin);
                }
            }

            $id = OptionDefinitionId::of($definition);
            $existing = $definition->values()->lockForUpdate()->get()->keyBy('id')->all();
            $kept = [];

            foreach ($payload->values as $draft) {
                if ($draft->id !== null && ! isset($existing[$draft->id->value])) {
                    throw InvalidOptionValues::ofAnotherOption($definition->slug);
                }

                if ($draft->id !== null) {
                    $kept[] = $draft->id->value;
                }
            }

            // Deleted first, so a kept or new value can take a deleted one's slug.
            foreach (array_diff_key($existing, array_flip($kept)) as $value) {
                $usage = OptionUsage::ofValues([$value->id]);

                if ($usage['variants'] > 0) {
                    throw OptionInUse::value($definition->slug, $value->slug, $usage['variants'], $usage['products']);
                }

                OptionUsage::withdraw([$value->id]);
                $value->delete();
                OptionDefinitionValueDeleted::dispatch(OptionDefinitionValueId::of($value), $id, origin: $origin);
            }

            // Slugs are unique per option, so values that change theirs first give them up:
            // swapping two would otherwise collide halfway.
            foreach ($payload->values as $draft) {
                if ($draft->id !== null && $existing[$draft->id->value]->slug !== $draft->slug) {
                    OptionDefinitionValue::query()->whereKey($draft->id->value)->update(['slug' => "__saving-{$draft->id->value}"]);
                }
            }

            foreach ($payload->values as $index => $draft) {
                $fields = ['name' => $draft->name, 'slug' => $draft->slug, 'swatch' => $draft->swatch, 'position' => $index + 1];

                if ($draft->id === null) {
                    $value = $definition->values()->create($fields);
                    OptionDefinitionValueCreated::dispatch(OptionDefinitionValueId::of($value), $id, origin: $origin);

                    continue;
                }

                $value = $existing[$draft->id->value];
                $changed = array_keys($value->fill($fields)->getDirty());
                $value->save();

                if ($changed !== []) {
                    OptionDefinitionValueUpdated::dispatch($draft->id, $id, $changed, origin: $origin);
                }
            }

            return $definition->refresh()->load('values');
        });
    }
}
