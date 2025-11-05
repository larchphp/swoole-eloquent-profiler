# Contributing to Swoole-Eloquent Profiler

Thank you for your interest in contributing to the Swoole-Eloquent Profiler! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

- Be respectful and constructive in all interactions
- Focus on what is best for the project and community
- Accept constructive criticism gracefully
- Show empathy towards other community members

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Swoole extension 5.0 or higher
- Composer
- Git

### Development Setup

1. Fork the repository on GitHub

2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/swoole-profiler.git
   cd swoole-profiler
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Create a feature branch:
   ```bash
   git checkout develop
   git checkout -b feature/your-feature-name
   ```

## Branch Workflow

This project uses Git Flow workflow:

- **develop**: Main development branch
- **main/master**: Production-ready releases (if applicable)
- **feature/***: New features (branched from develop)
- **bugfix/***: Bug fixes (branched from develop)
- **hotfix/***: Critical fixes for production (branched from main)

### Branch Naming Convention

- `feature/descriptive-name` - For new features
- `bugfix/issue-number-description` - For bug fixes
- `hotfix/critical-issue` - For critical fixes
- `refactor/component-name` - For refactoring

Examples:
- `feature/pool-metrics-enhancement`
- `bugfix/23-transaction-tracking`
- `refactor/storage-layer`

## Coding Standards

### PHP Style Guide

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Use type hints for all parameters and return types
- Write self-documenting code with clear variable names

### Code Example

```php
<?php

declare(strict_types=1);

namespace SwooleProfiler\Example;

/**
 * Example class demonstrating coding standards
 */
class ExampleClass
{
    public function __construct(
        private readonly string $name,
        private int $count = 0,
    ) {
    }

    /**
     * Example method with proper type hints and documentation
     */
    public function processData(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            if ($this->isValid($item)) {
                $result[] = $this->transform($item);
            }
        }

        return $result;
    }

    private function isValid(mixed $item): bool
    {
        return !empty($item);
    }

    private function transform(mixed $item): string
    {
        return (string)$item;
    }
}
```

### Documentation Standards

- All public methods must have docblocks
- Use clear, concise English
- Include `@param` and `@return` tags where appropriate
- Add usage examples for complex features

### Comments

- Write comments in English
- Explain **why**, not **what** (code should be self-explanatory)
- Use inline comments sparingly
- Keep comments up-to-date with code changes

Good comment:
```php
// Use transaction to ensure atomicity across multiple queries
$connection->beginTransaction();
```

Bad comment:
```php
// Start transaction
$connection->beginTransaction();
```

## Commit Guidelines

### Commit Message Format

Each commit message should be a single imperative sentence in English:

```
Add connection pool metrics tracking
```

```
Fix transaction rollback not recording properly
```

```
Refactor storage layer for better performance
```

### Commit Message Rules

1. Use imperative mood ("Add feature" not "Added feature")
2. Keep it concise (50-72 characters ideal)
3. Start with a capital letter
4. No period at the end
5. One logical change per commit

### Examples

✓ Good:
- `Add HTML reporter for web dashboard`
- `Fix memory leak in profiler storage`
- `Update documentation with Laravel examples`
- `Refactor query profile data class`

✗ Bad:
- `Added some stuff`
- `Fixed bug`
- `Updates`
- `WIP`

## Testing

### Running Tests

```bash
composer test
```

### Writing Tests

- Write unit tests for all new features
- Aim for high code coverage (>80%)
- Use meaningful test names that describe what is being tested
- Follow Arrange-Act-Assert pattern

Example test:

```php
<?php

namespace SwooleProfiler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Profiler;

class ProfilerTest extends TestCase
{
    public function test_profiler_records_query_successfully(): void
    {
        // Arrange
        $profiler = Profiler::getInstance();
        $profiler->enable();

        // Act
        $profiler->recordQuery(
            sql: 'SELECT * FROM users',
            bindings: [],
            duration: 10.5,
        );

        // Assert
        $queries = $profiler->getQueries();
        $this->assertCount(1, $queries);
        $this->assertEquals('SELECT * FROM users', $queries[0]->sql);
        $this->assertEquals(10.5, $queries[0]->duration);
    }
}
```

## Pull Request Process

### Before Submitting

1. Ensure all tests pass
2. Update documentation if needed
3. Add examples for new features
4. Follow coding standards
5. Write clear commit messages

### Submitting a Pull Request

1. Push your branch to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

2. Open a Pull Request on GitHub against the `develop` branch

3. Fill out the PR template with:
   - Description of changes
   - Related issue numbers
   - Testing steps
   - Screenshots (if applicable)

4. Wait for review and address feedback

### PR Title Format

Use the same format as commit messages:
- `Add feature X`
- `Fix bug in component Y`
- `Update documentation for Z`

### Review Process

- Maintainers will review your PR
- Address all review comments
- Keep the PR scope focused (one feature/fix per PR)
- Be patient and responsive

## Adding New Features

### Feature Checklist

When adding a new feature, ensure you:

- [ ] Create a feature branch from `develop`
- [ ] Implement the feature with tests
- [ ] Update relevant documentation
- [ ] Add usage examples
- [ ] Follow coding standards
- [ ] Write clear commit messages
- [ ] Submit a PR with description

### Feature Documentation

New features should include:

1. Code implementation
2. Unit tests
3. Usage example in `examples/` directory
4. Documentation in README.md
5. API documentation in docblocks

## Bug Reports

### Before Reporting

- Search existing issues to avoid duplicates
- Test against the latest version
- Gather necessary information

### Bug Report Template

```markdown
**Description**
Clear description of the bug

**Steps to Reproduce**
1. Step one
2. Step two
3. Step three

**Expected Behavior**
What you expected to happen

**Actual Behavior**
What actually happened

**Environment**
- PHP version:
- Swoole version:
- Library version:

**Additional Context**
Any other relevant information
```

## Project Structure

```
swoole-profiler/
├── src/
│   ├── Data/              # Data classes (QueryProfile, etc.)
│   ├── Storage/           # Storage and aggregation
│   ├── Decorators/        # Connection and pool decorators
│   ├── Reporters/         # Output formatters
│   ├── Laravel/           # Laravel integration
│   └── Profiler.php       # Main profiler class
├── config/                # Configuration files
├── examples/              # Usage examples
├── tests/                 # Unit tests
│   └── Unit/
├── composer.json
├── README.md
└── CONTRIBUTING.md
```

## Development Tips

### Working with Coroutines

- Always use `\Swoole\Coroutine::getCid()` to get coroutine ID
- Use `CoroutineContext` for per-coroutine storage
- Never use blocking operations in coroutine context
- Test with multiple concurrent coroutines

### Performance Considerations

- Profile your changes with real workloads
- Minimize memory allocations
- Use lazy loading where appropriate
- Avoid unnecessary loops and array operations

### Debugging

```php
// Enable Swoole debug mode
ini_set('swoole.display_errors', 'On');

// Use var_dump or print_r for debugging
var_dump($profiler->getMetrics());

// Check coroutine context
$cid = \Swoole\Coroutine::getCid();
echo "Coroutine ID: {$cid}\n";
```

## Release Process

Releases are managed by maintainers:

1. Version bump in `composer.json`
2. Update CHANGELOG.md
3. Tag release in Git
4. Publish to Packagist

## Questions?

If you have questions:

1. Check the README.md
2. Review existing issues
3. Open a discussion on GitHub
4. Ask in the community chat (if available)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Recognition

Contributors will be recognized in:
- GitHub contributors page
- Release notes for significant contributions
- Project documentation (for major features)

Thank you for contributing to Swoole-Eloquent Profiler!
