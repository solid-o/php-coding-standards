Coding standards
================

This package contains the requirements for the suite coding standards
and code analyzer packages (such as phpstan or php_codesniffer).

This package MUST be included as dev requirement in all PHP solido suite packages.

## PHPStan Configuration

When using enhanced DTOs, PHPStan should be configured with DTO namespaces:

```neon
# phpstan.neon

parameters:
    solido:
        dto_namespaces:
            - My\Application\DTO
            - App\Models
        excluded_interfaces:
            - My\Application\DTO\NonDTOInterface
```
