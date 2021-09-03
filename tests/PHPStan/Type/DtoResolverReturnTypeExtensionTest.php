<?php declare(strict_types=1);

namespace Tests\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use Solido\CodingStandards\PHPStan\Type\DtoResolverReturnTypeExtension;

class DtoResolverReturnTypeExtensionTest extends TypeInferenceTestCase
{
    /**
     * @dataProvider dataFileAsserts
     * @param string $assertType
     * @param string $file
     * @param mixed ...$args
     */
    public function testFileAsserts(
        string $assertType,
        string $file,
               ...$args
    ): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public function dataFileAsserts(): iterable
    {
        yield from $this->gatherAssertTypes(__DIR__ . '/../data/dto_resolver.php');
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../../phpstan.neon',
        ];
    }
}
