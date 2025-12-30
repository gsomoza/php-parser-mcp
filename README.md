# PHP Parser MCP

A Model Context Protocol (MCP) server that provides automated PHP refactoring tools powered by [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser).

## Features

- 🔧 **Extract Method**: Extract code blocks into separate methods
- 📦 **Extract Variable**: Extract expressions into named variables
- ✏️ **Rename Variable**: Safely rename variables across scopes
- 🌳 **Parse/Dump AST**: Parse PHP code and inspect Abstract Syntax Tree
- 🚀 **PHP 8.1+**: Built with modern PHP features
- 📜 **PHP 7.1+ Parsing**: Can parse and refactor PHP 7.1+ code
- 🔌 **MCP Protocol**: Seamless integration with MCP clients

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer install
```

## Usage

### Starting the MCP Server

The server uses stdio transport for communication:

```bash
php bin/php-parser-mcp
```

### Available Tools

#### parse_php

Parse a PHP file and return the Abstract Syntax Tree (AST).

**Parameters:**
- `file` (string): Path to the PHP file to parse

**Example:**
```json
{
  "file": "/path/to/file.php"
}
```

#### extract_method

Extract a block of code into a separate method.

**Parameters:**
- `file` (string): Path to the PHP file
- `startLine` (int): Starting line number
- `endLine` (int): Ending line number
- `methodName` (string): Name for the new method

#### extract_variable

Extract an expression into a named variable.

**Parameters:**
- `file` (string): Path to the PHP file
- `line` (int): Line number of the expression
- `column` (int): Column number of the expression
- `variableName` (string): Name for the new variable

#### rename_variable

Rename a variable throughout its scope.

**Parameters:**
- `file` (string): Path to the PHP file
- `line` (int): Line number where variable is used
- `oldName` (string): Current variable name
- `newName` (string): New variable name

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

### Code Quality

This project uses several code quality tools:

#### PHP Parallel Lint

Check PHP files for syntax errors:

```bash
composer lint
```

#### PHP-CS-Fixer

Check code style compliance with PER-CS2.0:

```bash
composer cs-check
```

Automatically fix code style issues:

```bash
composer cs-fix
```

#### PHPStan

Run static analysis (level 8):

```bash
composer phpstan
```

#### Mutation Testing

Run mutation testing with Infection:

```bash
composer infection
```

#### Run All Checks

Run all quality assurance checks at once:

```bash
composer qa
```

Configuration files:
- `.php-cs-fixer.dist.php` - PHP-CS-Fixer configuration
- `phpstan.neon` - PHPStan configuration
- `phpunit.xml.dist` - PHPUnit configuration
- `infection.json5` - Infection configuration

## Development

### Project Structure

```
php-parser-mcp/
├── bin/
│   └── php-parser-mcp          # Server entry point
├── src/
│   ├── Server.php              # Main MCP server class
│   └── Tools/                  # MCP tool implementations
│       └── ParseTool.php       # Parse PHP code tool
├── tests/
│   └── Tools/
│       └── ParseToolTest.php   # Tests for parse tool
├── composer.json
├── phpunit.xml
└── README.md
```

### Architecture

This server uses:
- **php-mcp/server**: For MCP protocol implementation
- **nikic/PHP-Parser**: For PHP code parsing and manipulation
- **PHP Attributes**: For automatic tool discovery

Tools are discovered automatically via PHP attributes, making it easy to add new refactoring capabilities.

### GitHub Copilot Setup

This repository includes a Copilot setup workflow (`.github/workflows/copilot-setup-steps.yml`) that configures the development environment for GitHub Copilot's coding agent.

#### Required Repository Secret

To enable Composer authentication, you need to add a `COMPOSER_TOKEN` secret to your repository:

1. Go to your repository's **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**
3. Name: `COMPOSER_TOKEN`
4. Value: Your GitHub personal access token or Composer authentication token
5. Click **Add secret**

The token will be used to authenticate Composer when installing private dependencies or accessing rate-limited package repositories.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

Gabriel Somoza - [gabriel@somoza.me](mailto:gabriel@somoza.me)
