<?php declare(strict_types=1);

use Solido\DtoManagement\InterfaceResolver\ResolverInterface;
use Tests\PHPStan\Fixtures\DTO\Contracts\ExampleDTOInterface;
use Tests\PHPStan\Fixtures\DTO\v1\v1_0\ExampleDTO;
use Tests\PHPStan\Fixtures\DTO\v1\v1_0\NonDTO;

use function PHPStan\Testing\assertType;

function (ResolverInterface $resolver) {
    assertType('mixed', $resolver->resolve('stdClass'));
    assertType('mixed', $resolver->resolve(stdClass::class));
    assertType('mixed', $resolver->resolve('unknown'));
    assertType('mixed', $resolver->resolve(UnknownClass::class));
    assertType('mixed', $resolver->resolve(NonDTO::class));
    assertType('mixed', $resolver->resolve('Tests\PHPStan\DTO\v1\v1_0\NonDTO'));
    assertType('mixed', $resolver->resolve(ExampleDTO::class));
    assertType('mixed', $resolver->resolve('Tests\PHPStan\DTO\v1\v1_0\ExampleDTO'));

    assertType('Tests\PHPStan\Fixtures\DTO\Contracts\ExampleDTOInterface', $resolver->resolve(ExampleDTOInterface::class));
    assertType('Tests\PHPStan\Fixtures\DTO\Contracts\ExampleDTOInterface', $resolver->resolve('Tests\PHPStan\Fixtures\DTO\Contracts\ExampleDTOInterface'));
};
