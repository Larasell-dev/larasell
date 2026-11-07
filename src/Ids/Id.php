<?php

namespace Larasell\Larasell\Ids;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Larasell\Larasell\Ids\Exceptions\InvalidId;
use Stringable;

/**
 * The ID of one kind of record, passed across module boundaries: to actions, in events,
 * exceptions and job payloads. A ProductId can't be handed where an OrderId is expected,
 * and two IDs with the same number but of different kinds are never equal.
 *
 * Each subclass names the one model it identifies. Inside a module, pass the model itself.
 */
abstract readonly class Id implements JsonSerializable, Stringable
{
    final public function __construct(public int $value)
    {
        if ($value < 1) {
            throw InvalidId::notPositive(static::class, (string) $value);
        }
    }

    /**
     * The model this ID identifies.
     *
     * @return class-string<Model>
     */
    abstract protected static function model(): string;

    public static function of(Model $model): static
    {
        $expected = static::model();

        if (! $model instanceof $expected) {
            throw InvalidId::wrongModel(static::class, $expected, $model::class);
        }

        $key = $model->getKey();

        if ($key === null) {
            throw InvalidId::unsaved(static::class, $model::class);
        }

        return new static((int) $key);
    }

    /**
     * Parse an ID from request input, a route parameter or a queue payload.
     */
    public static function fromString(string $value): static
    {
        if ($value === '0') {
            throw InvalidId::notPositive(static::class, $value);
        }

        $int = preg_match('/^[1-9][0-9]*$/', $value) === 1
            ? filter_var($value, FILTER_VALIDATE_INT)
            : false;

        if ($int === false) {
            throw InvalidId::malformed(static::class, $value);
        }

        return new static($int);
    }

    /**
     * @param  iterable<int>  $values
     * @return list<static>
     */
    public static function many(iterable $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            $ids[] = new static($value);
        }

        return $ids;
    }

    /**
     * The numbers of many IDs, e.g. for whereIn().
     *
     * @param  iterable<static>  $ids
     * @return list<int>
     */
    public static function values(iterable $ids): array
    {
        $values = [];

        foreach ($ids as $id) {
            $values[] = $id->value;
        }

        return $values;
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function jsonSerialize(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
