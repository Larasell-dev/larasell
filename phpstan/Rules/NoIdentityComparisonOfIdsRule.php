<?php

namespace Larasell\Larasell\PHPStan\Rules;

use Larasell\Larasell\Ids\Id;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * IDs are objects, so === and !== compare whether they are the same instance, not the same ID:
 * two ProductId(5) are not identical. Compare them with equals(), or their ->value.
 *
 * @implements Rule<BinaryOp>
 */
final class NoIdentityComparisonOfIdsRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof BinaryOp\Identical && ! $node instanceof BinaryOp\NotIdentical) {
            return [];
        }

        $left = $scope->getType($node->left);
        $right = $scope->getType($node->right);

        if ($left->isNull()->yes() || $right->isNull()->yes() || (! $this->isId($left) && ! $this->isId($right))) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Comparing IDs with {$node->getOperatorSigil()} compares instances, so two equal IDs never match: use equals() or compare their ->value.")
                ->identifier('larasell.idIdentity')
                ->build(),
        ];
    }

    private function isId(Type $type): bool
    {
        $type = TypeCombinator::removeNull($type);

        return $type->isObject()->yes() && new ObjectType(Id::class)->isSuperTypeOf($type)->yes();
    }
}
