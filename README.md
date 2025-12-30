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

### Code Quality

This project uses several code quality tools:

#### PHP_CodeSniffer (PHPCS)

Check code style compliance with PSR-12:

```bash
composer phpcs
```

Automatically fix code style issues:

```bash
composer phpcbf
```

#### PHPStan

Run static analysis (level 8):

```bash
composer phpstan
```

Configuration files:
- `phpcs.xml` - PHPCS configuration
- `phpstan.neon` - PHPStan configuration

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

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

Gabriel Somoza - [gabriel@somoza.me](mailto:gabriel@somoza.me)
