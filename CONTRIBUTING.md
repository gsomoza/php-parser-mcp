# Contributing to PHP Refactor MCP

Thank you for your interest in contributing to PHP Refactor MCP! This document provides guidelines and information for contributors.

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer

### Installation

1. Clone the repository:
```bash
git clone https://github.com/gsomoza/php-refactor-mcp.git
cd php-refactor-mcp
```

2. Install dependencies:
```bash
composer install
```

## Testing

Run the test suite:

```bash
composer test
```

Or with PHPUnit directly:

```bash
vendor/bin/phpunit
```

Run tests with code coverage:

```bash
composer test:coverage
```

### Fixture-Based Testing

This project uses a fixture-based testing approach for refactoring tools, making it easy to add new test cases without writing repetitive test code.

#### How It Works

Tests for refactoring tools (e.g., `RenameVariableTool`, `ExtractMethodTool`) use fixtures stored in `tests/Fixtures/{ToolName}/`. Each fixture is a PHP file containing:
1. Test parameters in comment headers
2. The PHP code to be refactored

The test framework automatically discovers all fixtures, executes the refactoring tool, and validates the output using snapshot testing.

#### Adding a New Test Case

To add a new test case for a refactoring tool:

1. Create a new PHP file in `tests/Fixtures/{ToolName}/{test_case_name}.php`
2. Add test parameters as comment headers
3. Add the PHP code to be refactored

**Example - Adding a rename variable test:**

```php
<?php
// line: 6
// oldName: $oldVar
// newName: $newVar

function test() {
    $oldVar = 1;
    $result = $oldVar + 2;
    return $result;
}
```

**Important:** Line numbers in parameters must account for the comment header lines. If your code starts at line 1 without comments, add 3 to get the actual line number (for the `<?php` line, the parameter comment lines).

4. Run the tests to generate the snapshot:

```bash
UPDATE_SNAPSHOTS=true composer test
```

That's it! The test framework will:
- Automatically discover your new fixture
- Execute the refactoring tool with the specified parameters
- Create a snapshot of the output
- Compare future runs against this snapshot

#### Parameter Format by Tool

**RenameVariableTool:**
```php
// line: {line_number}
// oldName: {old_variable_name}
// newName: {new_variable_name}
```

**ExtractMethodTool:**
```php
// range: {start_line}-{end_line}
// methodName: {extracted_method_name}
```

**IntroduceVariableTool / ExtractVariableTool:**
```php
// position: {line}:{column}
// variableName: {variable_name}
```

#### Error Cases

Error cases (e.g., file not found, invalid parameters) should be implemented as traditional test methods in the test class itself, not as fixtures. This allows for specific error assertions beyond just snapshot matching.

Example:

```php
public function testRenameVariableFileNotFound(): void
{
    $result = $this->tool->rename('/nonexistent/file.php', '1', '$old', '$new');

    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('File not found', $result['error']);
}
```

#### Updating Snapshots

When refactoring code changes the output format, update all snapshots:

```bash
UPDATE_SNAPSHOTS=true composer test
```

#### Benefits of Fixture-Based Testing

- **Easy to add tests**: Just drop a PHP file with the test case
- **Clear test data**: Fixtures are separate from test logic
- **Automatic validation**: Snapshots ensure output correctness
- **No duplication**: Test execution logic is shared across all fixtures
- **Maintainable**: Changes to tool signatures only require updating the base test class

## Code Quality

This project uses several code quality tools to maintain high standards:

### PHP Parallel Lint

Check PHP files for syntax errors:

```bash
composer lint
```

### PHP-CS-Fixer

Check code style compliance with PER-CS2.0:

```bash
composer cs-check
```

Automatically fix code style issues:

```bash
composer cs-fix
```

### PHPStan

Run static analysis (level 8):

```bash
composer phpstan
```

### Mutation Testing

Run mutation testing with Infection:

```bash
composer infection
```

### Run All Checks

Run all quality assurance checks at once:

```bash
composer qa
```

## Configuration Files

- `.php-cs-fixer.dist.php` - PHP-CS-Fixer configuration
- `phpstan.neon` - PHPStan configuration
- `phpunit.xml.dist` - PHPUnit configuration
- `infection.json5` - Infection configuration

## Project Structure

```
php-refactor-mcp/
├── bin/
│   └── php-refactor-mcp          # Server entry point
├── src/
│   ├── Server.php              # Main MCP server class
│   ├── Helpers/                # Helper classes
│   └── Tools/                  # MCP tool implementations
│       ├── ParseTool.php       # Parse PHP code tool
│       ├── ExtractMethodTool.php
│       ├── ExtractVariableTool.php
│       └── RenameVariableTool.php
├── tests/
│   └── Tools/
│       └── *Test.php           # Tests for tools
├── composer.json
├── phpunit.xml.dist
└── README.md
```

## Architecture

This server uses:
- **php-mcp/server**: For MCP protocol implementation
- **nikic/PHP-Parser**: For PHP code parsing and manipulation
- **PHP Attributes**: For automatic tool discovery

Tools are discovered automatically via PHP attributes, making it easy to add new refactoring capabilities.

## Adding a New Tool

To add a new refactoring tool:

1. Create a new class in `src/Tools/` directory
2. Add the `#[McpTool]` attribute to the method that implements the tool
3. Add `#[Schema]` attributes to describe parameters
4. Implement the refactoring logic
5. Add tests in `tests/Tools/`
6. Update README.md with the new tool information

Example:

```php
<?php

namespace Somoza\PhpRefactorMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class MyRefactoringTool
{
    #[McpTool(
        name: 'my_tool',
        description: 'Description of what the tool does'
    )]
    public function refactor(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file'
        )]
        string $file
    ): array {
        // Implementation
        return [
            'success' => true,
            'message' => 'Refactoring completed'
        ];
    }
}
```

## Coding Standards

- Follow PSR-4 autoloading standards
- Follow PER-CS2.0 coding style guide
- Use PHP 8.1+ features where appropriate
- Maintain compatibility with PHP 7.1 code parsing
- Write comprehensive tests for all features
- Document all public methods and classes with PHPDoc

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Make your changes
4. Run all quality checks (`composer qa`)
5. Commit your changes with clear messages
6. Push to your fork
7. Open a Pull Request with a clear description

## GitHub Copilot Setup

This repository includes a Copilot setup workflow (`.github/workflows/copilot-setup-steps.yml`) that configures the development environment for GitHub Copilot's coding agent.

### Required Repository Secret

To enable Composer authentication, you need to add a `COMPOSER_TOKEN` secret to your repository:

1. Go to your repository's **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**
3. Name: `COMPOSER_TOKEN`
4. Value: Your GitHub personal access token or Composer authentication token
5. Click **Add secret**

The token will be used to authenticate Composer when installing private dependencies or accessing rate-limited package repositories.

## Questions?

Feel free to open an issue for questions, suggestions, or discussions about the project.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
