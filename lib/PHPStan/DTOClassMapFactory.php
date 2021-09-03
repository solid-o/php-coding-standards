<?php

declare(strict_types=1);

namespace Solido\CodingStandards\PHPStan;

use Composer\Autoload\ClassLoader;
use Kcs\ClassFinder\Finder\ComposerFinder;
use PhpParser\Node\Expr\New_;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader as ErrorHandlerClassLoader;

use function array_combine;
use function array_fill;
use function array_push;
use function array_unique;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function is_array;
use function is_string;
use function preg_match;
use function spl_autoload_functions;
use function str_replace;
use function strpos;

class DTOClassMapFactory
{
    /** @var string[] */
    private array $dtoNamespaces;
    /** @var string[] */
    private array $excludedInterfaces;
    /** @var string[] */
    private array $dtoClasses;

    /**
     * @param string[] $dtoNamespaces
     * @param string[] $excludedInterfaces
     */
    public function __construct(array $dtoNamespaces, array $excludedInterfaces)
    {
        $this->dtoNamespaces = $dtoNamespaces;
        $this->excludedInterfaces = array_combine($excludedInterfaces, array_fill(0, count($excludedInterfaces), true));

        $this->buildMap();
    }

    public function isDtoClass(string $className): bool
    {
        return isset($this->dtoClasses[$className]);
    }

    private function buildMap(): void
    {
        $classes = [];
        foreach ($this->dtoNamespaces as $namespace) {
            array_push($classes, ...$this->processNamespace($namespace));
        }

        $classes = array_unique($classes);
        $this->dtoClasses = array_combine($classes, array_fill(0, count($classes), true));
    }

    /**
     * @return string[]
     * @phpstan-return class-string[]
     */
    private function processNamespace(string $namespace): array
    {
        $finder = new ComposerFinder(self::getValidLoader());
        $finder->inNamespace($namespace);
        [$interfaces, $modelsByInterface] = $this->collectInterfaces($namespace, $finder);

        /** @phpstan-var class-string[] $locators */
        $classes = [];
        foreach ($modelsByInterface as $interface => $versions) {
            if (! isset($interfaces[$interface])) {
                continue;
            }

            array_push($classes, ...array_values($versions));
        }

        return array_unique($classes);
    }

    /**
     * @return array<string, ReflectionClass>|array<string, array<string, string>>[]
     * @phpstan-return array{0: array<class-string, ReflectionClass>, 1: array<class-string, array<string, string>>}
     */
    private function collectInterfaces(string $namespace, ComposerFinder $finder): array
    {
        $versionPattern = '/^' . str_replace('\\', '\\\\', $namespace) . '\\\\v(.+?)\\\\v(.+?)\\\\/';

        $interfaces = [];
        $modelsByInterface = [];

        /**
         * @phpstan-var class-string $class
         */
        foreach ($finder as $class => $reflector) {
            assert(is_string($class));
            assert($reflector instanceof ReflectionClass);

            if ($reflector->isInterface()) {
                if (! isset($this->excludedInterfaces[$reflector->getName()])) {
                    $interfaces[$class] = $reflector;
                }

                continue;
            }

            if (! preg_match($versionPattern, $class, $m)) {
                continue;
            }

            foreach ($reflector->getInterfaces() as $interface) {
                $modelsByInterface[$interface->getName()][] = $reflector->getName();
            }
        }

        return [$interfaces, $modelsByInterface];
    }

    /**
     * Try to get a registered instance of composer ClassLoader.
     *
     * @throws RuntimeException if composer CLassLoader cannot be found.
     */
    private static function getValidLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $autoloadFn) {
            if (is_array($autoloadFn)) {
                if (class_exists(DebugClassLoader::class) && $autoloadFn[0] instanceof DebugClassLoader) {
                    $autoloadFn = $autoloadFn[0]->getClassLoader();
                } elseif (class_exists(ErrorHandlerClassLoader::class) && $autoloadFn[0] instanceof ErrorHandlerClassLoader) {
                    $autoloadFn = $autoloadFn[0]->getClassLoader();
                }
            }

            if (! is_array($autoloadFn) || ! ($autoloadFn[0] instanceof ClassLoader)) {
                continue;
            }

            $loader = $autoloadFn[0];
            $file = $loader->findFile(New_::class);
            if ($file === false || strpos($file, 'phar://') !== 0) {
                return $loader;
            }
        }

        throw new RuntimeException('Cannot find a valid composer class loader in registered autoloader functions. Cannot continue.');
    }
}
