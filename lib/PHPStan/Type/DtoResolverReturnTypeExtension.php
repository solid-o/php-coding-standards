<?php

declare(strict_types=1);

namespace Solido\CodingStandards\PHPStan\Type;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Solido\DtoManagement\InterfaceResolver\ResolverInterface;

class DtoResolverReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return ResolverInterface::class;
    }

    public function isMethodSupported(MethodReflection $reflection): bool
    {
        return $reflection->getName() === 'resolve';
    }

    public function getTypeFromMethodCall(MethodReflection $reflection, MethodCall $methodCall, Scope $scope): Type
    {
        if (count($methodCall->args) === 0) {
            return ParametersAcceptorSelector::selectSingle($reflection->getVariants())->getReturnType();
        }

        $arg = $methodCall->args[0]->value;
        // Care only for ::class parameters, we can not guess types for random strings.
        if ($arg instanceof ClassConstFetch) {
            $value = $scope->getType($methodCall->args[0]->value)->getValue();
        } elseif ($arg instanceof String_) {
            $value = $arg->value;
        } else {
            return ParametersAcceptorSelector::selectSingle($reflection->getVariants())->getReturnType();
        }

        $broker = Broker::getInstance();
        if (! $broker->hasClass($value)) {
            return ParametersAcceptorSelector::selectSingle($reflection->getVariants())->getReturnType();
        }

        $classReflection = $broker->getClass($value);
        if (! $classReflection->isInterface()) {
            return ParametersAcceptorSelector::selectSingle($reflection->getVariants())->getReturnType();
        }

        return new ObjectType($value);
    }
}
