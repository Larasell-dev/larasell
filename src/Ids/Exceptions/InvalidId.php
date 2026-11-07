<?php

namespace Larasell\Larasell\Ids\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Ids\Id;

final class InvalidId extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    /**
     * @param  class-string<Id>  $id
     */
    public static function notPositive(string $id, string $value): static
    {
        return self::structured(
            errorCode: 'larasell.id.not-positive',
            message: "A {$id} must be a positive number, [{$value}] given.",
            why: 'Records are numbered from 1, so no record can have this ID.',
            fix: 'Pass the key of a saved record, e.g. '.class_basename($id).'::of($model).',
        );
    }

    /**
     * @param  class-string<Id>  $id
     */
    public static function malformed(string $id, string $value): static
    {
        return self::structured(
            errorCode: 'larasell.id.malformed',
            message: "[{$value}] is not a valid {$id}.",
            why: 'An ID is a positive whole number without sign, leading zeros or decimals, and it must fit in an integer.',
            fix: 'Check where the value comes from; reject the input before it reaches this point if it comes from a user.',
        );
    }

    /**
     * @param  class-string<Id>  $id
     */
    public static function wrongModel(string $id, string $expected, string $given): static
    {
        return self::structured(
            errorCode: 'larasell.id.wrong-model',
            message: "A {$id} identifies a {$expected}, but a {$given} was given.",
            why: 'Taking the key of another kind of record would point the ID at an unrelated row with the same number.',
            fix: 'Use the ID class that belongs to '.class_basename($given).', or pass the right model.',
        );
    }

    /**
     * @param  class-string<Id>  $id
     */
    public static function unsaved(string $id, string $model): static
    {
        return self::structured(
            errorCode: 'larasell.id.unsaved',
            message: "The {$model} is not saved yet, so it has no {$id}.",
            why: 'A record only gets its ID when it is inserted.',
            fix: 'Save the model first, or create it through its action, which returns the saved model.',
        );
    }
}
