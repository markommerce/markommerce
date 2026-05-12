# Code Standards

Markommerce inherits Marko's full code standards. This file captures the rules that require human judgment — the linter and Rector handle formatting automatically.

## Linting Tools

```bash
./vendor/bin/phpcs                        # Check for issues
./vendor/bin/php-cs-fixer fix             # Auto-fix formatting
./vendor/bin/phpstan analyse              # Static analysis (level 8)
./vendor/bin/rector process               # Auto-refactor
```

## Auto-Fixed by Toolchain

Do NOT spend time checking these — the toolchain handles them:

- Constructor property promotion (Rector)
- Import organization, unused imports (php-cs-fixer)
- Multiline method signatures with trailing commas (PHPCS)
- PSR-12 formatting, blank lines, whitespace (php-cs-fixer)
- String interpolation curly brace removal (php-cs-fixer)

## PHP Version Requirements

- Minimum: **PHP 8.5**
- Use PHP 8.5 features: pipe operator (`|>`), `array_first()`, `array_last()`, `array_find()`, `array_any()`, `array_all()`

## Rules That Require Judgment

### 1. Strict Types Required
Every PHP file: `declare(strict_types=1);`

### 2. Constructor Injection Only
```php
// CORRECT
public function __construct(
    private ProductRepositoryInterface $productRepository,
) {}

// WRONG — service locator
$repo = Container::get(ProductRepositoryInterface::class);
```

### 3. Interface Parameter Naming
Parameter name = interface name minus `Interface` suffix, camelCase:

```php
// CORRECT
public function __construct(
    private ProductRepositoryInterface $productRepository,
    private PricingServiceInterface $pricingService,
) {}

// WRONG
public function __construct(
    private ProductRepositoryInterface $repo,
    private PricingServiceInterface $pricing,
) {}
```

### 4. `readonly class` When All Properties Are Immutable

```php
// CORRECT — all properties immutable → readonly class
readonly class Money
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {}
}

// WRONG — individual readonly when all are immutable
class Money
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {}
}
```

### 5. No `final` Classes
`final` blocks Marko Preferences (extensibility). Only use for security-critical classes and document why.

### 6. Type Declarations Required
All parameters, return types, and properties. Use the **narrowest** type possible:

```php
// CORRECT
public function find(int $id): ?Product {}

// WRONG — overly broad
public function find(int $id): mixed {}
```

### 7. Typed Constants (PHP 8.3+)
```php
// CORRECT
private const string STATUS_ACTIVE = 'active';
private const int MAX_IMAGES = 10;

// WRONG
private const STATUS_ACTIVE = 'active';
```

### 8. `@throws` Tags Required
Every method containing `throw` or calling a method that propagates exceptions:

```php
/**
 * @throws ProductNotFoundException|ConfigNotFoundException
 */
public function get(int $id): Product
{
    $product = $this->productRepository->find($id);
    if ($product === null) {
        throw ProductNotFoundException::forId($id);
    }
    return $product;
}
```

Rules:
- Always import exception classes — never use FQCNs in `@throws`
- Pipe-delimit multiple exceptions on one line
- Test files are exempt

### 9. No Traits
Use explicit composition — inject dependencies, don't mix in behavior:

```php
// WRONG
class ProductService { use LoggingTrait; }

// CORRECT
class ProductService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}
}
```

### 10. No Magic Methods
Avoid `__get`, `__set`, `__call`, `__callStatic`. Be explicit.

### 11. No Dead Code
Remove unused variables, unused constructor dependencies, unused method parameters.

### 12. Asymmetric Visibility (PHP 8.4+)
Prefer `public private(set)` over getter methods for simple read-only exposure:

```php
class Product
{
    public private(set) string $name;
    public private(set) int $price;
}
```

### 13. Prefer PHP 8.5 Array Functions Over Loops

```php
// CORRECT
return array_find($this->products, fn (Product $p) => $p->id === $id);

// WRONG
foreach ($this->products as $product) {
    if ($product->id === $id) { return $product; }
}
return null;
```

### 14. SQL Identifier Validation
Dynamic table/column names cannot use parameter binding — validate against a safe pattern:

```php
private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
```

Sort directions must be validated against an allowlist `['asc', 'desc']`.

## Exception Standards

All Markommerce exceptions extend `MarkoException` with three named parameters:

```php
class ProductNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Product with ID $id not found",
            context: 'While loading product',
            suggestion: 'Verify the product ID exists and is not archived',
        );
    }
}
```

## Naming Conventions

| Element          | Convention                                         |
|------------------|----------------------------------------------------|
| Classes          | PascalCase                                         |
| Interfaces       | PascalCase + `Interface` suffix                    |
| Exceptions       | PascalCase + `Exception` suffix                    |
| Methods          | camelCase                                          |
| Properties       | camelCase                                          |
| Constants        | SCREAMING_SNAKE_CASE                               |
| Files            | Match class name exactly                           |
| Driver classes   | `{Driver}{Component}` (e.g. `StripePaymentGateway`) |

## Git Hooks

Set up pre-commit hooks (Rector → php-cs-fixer → PHPCS) after cloning:

```bash
git config core.hooksPath .githooks
```
