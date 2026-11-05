<?php

namespace Larasell\Larasell\PHPStan\Rules;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * L6: domain events (classes in an Events namespace of Larasell) carry IDs and facts, never models.
 * Listeners then load what they need by ID, so a stale copy of a model cannot do harm.
 *
 * @implements Rule<InClassNode>
 */
final class DomainEventsCarryNoModelsRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflection) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();

        if (! str_starts_with($class->getName(), 'Larasell\\Larasell\\') || ! str_contains($class->getName(), '\\Events\\')) {
            return [];
        }

        $errors = [];

        foreach ($class->getNativeReflection()->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $type = $class->getProperty($property->getName(), $scope)->getReadableType();

            foreach ($type->getReferencedClasses() as $referenced) {
                if ($this->isModelOrCollection($referenced)) {
                    $errors[] = RuleErrorBuilder::message("The domain event {$class->getDisplayName()} carries [{$referenced}] in \${$property->getName()}: carry its ID and the facts listeners need instead.")
                        ->identifier('larasell.domainEventModel')
                        ->line($node->getStartLine())
                        ->build();

                    break;
                }
            }
        }

        return $errors;
    }

    private function isModelOrCollection(string $class): bool
    {
        if (! $this->reflection->hasClass($class)) {
            return false;
        }

        $reflection = $this->reflection->getClass($class);

        return $reflection->is(Model::class) || $reflection->is(Collection::class);
    }
}
