<?php

namespace Larasell\Larasell\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionMethod;

/**
 * IDs that cross a module boundary are typed (ProductId, OrderId, ...), so a number meant for one
 * kind of record cannot reach another. Applies to the public parameters and properties of actions,
 * events, exceptions, jobs and workflows: any named like an ID, e.g. $productId or $categoryIds,
 * must not be an int or a list of ints.
 *
 * @implements Rule<InClassNode>
 */
final class BoundaryIdsAreTypedRule implements Rule
{
    private const BOUNDARIES = ['\\Actions\\', '\\Events\\', '\\Exceptions\\', '\\Jobs\\', '\\Workflows\\'];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $name = $class->getName();

        if (! str_starts_with($name, 'Larasell\\Larasell\\') || array_filter(self::BOUNDARIES, fn (string $part): bool => str_contains($name, $part)) === []) {
            return [];
        }

        $errors = [];

        foreach ($class->getNativeReflection()->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            foreach ($class->getMethod($method->getName(), $scope)->getVariants()[0]->getParameters() as $parameter) {
                if ($this->isUntypedId($parameter->getName(), $parameter->getType())) {
                    $errors[] = $this->error("The parameter \${$parameter->getName()} of {$class->getDisplayName()}::{$method->getName()}()", $node->getStartLine());
                }
            }
        }

        foreach ($class->getNativeReflection()->getProperties() as $property) {
            if (! $property->isPublic() || $property->isPromoted() || $property->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            if ($this->isUntypedId($property->getName(), $class->getProperty($property->getName(), $scope)->getReadableType())) {
                $errors[] = $this->error("The property \${$property->getName()} of {$class->getDisplayName()}", $node->getStartLine());
            }
        }

        return $errors;
    }

    private function isUntypedId(string $name, Type $type): bool
    {
        if (preg_match('/(^id|Id)s?$/', $name) !== 1) {
            return false;
        }

        $type = TypeCombinator::removeNull($type);

        return $type->isInteger()->yes()
            || ($type->isArray()->yes() && $type->getIterableValueType()->isInteger()->yes());
    }

    private function error(string $subject, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message("{$subject} is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.")
            ->identifier('larasell.untypedId')
            ->line($line)
            ->build();
    }
}
