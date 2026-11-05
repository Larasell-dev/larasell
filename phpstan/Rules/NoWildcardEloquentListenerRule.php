<?php

namespace Larasell\Larasell\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * L1: no listener for the Eloquent events of every model, such as listen('eloquent.saved: *').
 * Modules react to another module's domain events instead.
 *
 * @implements Rule<CallLike>
 */
final class NoWildcardEloquentListenerRule implements Rule
{
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall)
            || ! $node->name instanceof Identifier
            || $node->name->toLowerString() !== 'listen'
            || $node->isFirstClassCallable()
            || ! isset($node->getArgs()[0])) {
            return [];
        }

        $errors = [];
        $type = $scope->getType($node->getArgs()[0]->value);
        $names = [...$type->getConstantStrings(), ...array_merge(...array_map(
            fn ($array) => $array->getValueTypes(),
            $type->getConstantArrays(),
        ))];

        foreach ($names as $name) {
            foreach ($name->getConstantStrings() as $string) {
                $value = $string->getValue();

                if (str_starts_with($value, 'eloquent.') && str_ends_with($value, '*')) {
                    $errors[] = RuleErrorBuilder::message("Do not listen to [{$value}]: react to the owning module's domain events instead.")
                        ->identifier('larasell.wildcardEloquentListener')
                        ->build();
                }
            }
        }

        return $errors;
    }
}
