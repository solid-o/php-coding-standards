<?php

declare(strict_types=1);

namespace Solido\CodingStandards\PHPStan\Rule\Construction;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use Solido\CodingStandards\PHPStan\DTOClassMapFactory;

use function array_map;
use function array_merge;
use function array_push;
use function assert;
use function sprintf;
use function strtolower;

class DisallowConstructionOfDTOObjects implements Rule
{
    private DTOClassMapFactory $dtoClassMapFactory;
    private ReflectionProvider $reflectionProvider;

    public function __construct(DTOClassMapFactory $dtoClassMapFactory, ReflectionProvider $reflectionProvider)
    {
        $this->dtoClassMapFactory = $dtoClassMapFactory;
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return Node\Expr\New_::class;
    }

    /**
     * @return RuleError[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Node\Expr\New_);

        $errors = [];
        foreach ($this->getClassNames($node, $scope) as [$class, $isName]) {
            array_push($errors, ...$this->checkClassName($class, $isName, $scope));
        }

        return $errors;
    }

    /**
     * @return array<int, array{string, bool}>
     */
    private function getClassNames(Node\Expr\New_ $node, Scope $scope): array
    {
        if ($node->class instanceof Name) {
            return [[(string) $node->class, true]];
        }

        if ($node->class instanceof Class_) {
            $anonymousClassType = $scope->getType($node);
            if (! $anonymousClassType instanceof TypeWithClassName) {
                throw new ShouldNotHappenException();
            }

            return [[$anonymousClassType->getClassName(), true]];
        }

        $type = $scope->getType($node->class);

        return array_merge(
            array_map(static fn (ConstantStringType $type): array => [$type->getValue(), true], TypeUtils::getConstantStrings($type)),
            array_map(static fn (string $name): array => [$name, false], TypeUtils::getDirectClassNames($type))
        );
    }

    /**
     * @return RuleError[]
     */
    private function checkClassName(string $class, bool $isName, Scope $scope): array
    {
        $lowercaseClass = strtolower($class);

        if ($lowercaseClass === 'static') {
            // "new static" is allowed.
            return [];
        }

        if ($lowercaseClass === 'self') {
            if (! $scope->isInClass()) {
                // Should be checked in another rule
                return [];
            }

            $classReflection = $scope->getClassReflection();
        } elseif ($lowercaseClass === 'parent') {
            if (! $scope->isInClass() || ! $scope->getClassReflection()->getParentClass()) {
                return [];
            }

            $classReflection = $scope->getClassReflection()->getParentClass();
        } else {
            if (! $this->reflectionProvider->hasClass($class)) {
                return [];
            }

            $classReflection = $this->reflectionProvider->getClass($class);
        }

        if ($classReflection->isInterface() || $classReflection->isAbstract()) {
            return [];
        }

        if (! $isName) {
            return [];
        }

        if (! $this->dtoClassMapFactory->isDtoClass($class)) {
            // Not a DTO-enabled class.
            return [];
        }

        return [RuleErrorBuilder::message(sprintf('Instantiation of class %s is disallowed: use the DTO resolver to create a new instance', $classReflection->getDisplayName()))->build()];
    }
}
