<?php

namespace Larasell\Larasell\PHPStan\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Larasell\Larasell\PHPStan\ModuleMap;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * L4: a module writes another module's models only through that module's actions. Reading them is fine.
 * Catches write methods called on a model, on a query builder of a model, or on a relation to one.
 *
 * @implements Rule<CallLike>
 */
final class NoCrossModuleModelWriteRule implements Rule
{
    /** Methods that write, on models, query builders, and relations. Reads are allowed, so this list must name every write. */
    private const WRITES = [
        'attach', 'create', 'createmany', 'createmanyquietly', 'createorfirst', 'createquietly', 'decrement',
        'decrementeach', 'decrementquietly', 'delete', 'deleteorfail', 'deletequietly', 'detach', 'firstorcreate',
        'forcecreate', 'forcecreatequietly', 'forcedelete', 'forcedeletequietly', 'increment', 'incrementeach',
        'incrementquietly', 'insert', 'insertgetid', 'insertorignore', 'insertorignoreusing', 'insertusing', 'push',
        'pushquietly', 'restore', 'restorequietly', 'save', 'savemany', 'savemanyquietly', 'saveorfail', 'savequietly',
        'sync', 'syncwithoutdetaching', 'syncwithpivotvalues', 'toggle', 'touch', 'touchquietly', 'update',
        'updateexistingpivot', 'updateorcreate', 'updateorfail', 'updateorinsert', 'updatequietly', 'upsert',
    ];

    /** Static methods on a model class that write, e.g. Product::create(). */
    private const STATIC_WRITES = [
        'create', 'createorfirst', 'createquietly', 'destroy', 'firstorcreate', 'forcecreate', 'forcecreatequietly',
        'insert', 'insertgetid', 'insertorignore', 'updateorcreate', 'upsert',
    ];

    public function __construct(
        private readonly ModuleMap $modules,
        private readonly ReflectionProvider $reflection,
    ) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall) || ! $node->name instanceof Identifier) {
            return [];
        }

        $caller = $this->modules->moduleOf($scope->getClassReflection()?->getName() ?? $scope->getNamespace().'\\');

        if ($caller === null) {
            return [];
        }

        $method = $node->name->toLowerString();
        $models = match (true) {
            $node instanceof MethodCall && in_array($method, self::WRITES, true) => $this->modelsOf($scope->getType($node->var)),
            $node instanceof StaticCall && $node->class instanceof Name && in_array($method, self::STATIC_WRITES, true) => $this->modelsOfClass($scope->resolveName($node->class)),
            default => [],
        };

        $errors = [];

        foreach ($models as $model) {
            $owner = $this->modules->moduleOf($model);

            if ($owner !== null && $owner !== $caller) {
                $errors[] = RuleErrorBuilder::message("{$caller} must not write [{$model}] of module {$owner} with {$node->name->toString()}(): call one of {$owner}'s actions.")
                    ->identifier('larasell.crossModuleWrite')
                    ->build();
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private function modelsOf(Type $type): array
    {
        $models = [];

        foreach ($type->getObjectClassNames() as $class) {
            array_push($models, ...$this->modelsOfClass($class));
        }

        foreach ([[Builder::class, 'TModel'], [Relation::class, 'TRelatedModel']] as [$generic, $template]) {
            foreach ($type->getTemplateType($generic, $template)->getObjectClassNames() as $class) {
                array_push($models, ...$this->modelsOfClass($class));
            }
        }

        return array_values(array_unique($models));
    }

    /** @return list<string> */
    private function modelsOfClass(string $class): array
    {
        return $class !== Model::class && $this->reflection->hasClass($class) && $this->reflection->getClass($class)->is(Model::class)
            ? [$class]
            : [];
    }
}
