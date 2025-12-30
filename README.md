# PHP Refactor MCP

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

### Integrating with MCP Clients

#### Claude Desktop

Add this configuration to your Claude Desktop config file:

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

**Linux**: `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "php-refactor": {
      "command": "php",
      "args": ["/absolute/path/to/php-refactor-mcp/bin/php-refactor-mcp"]
    }
  }
}
```

Replace `/absolute/path/to/php-refactor-mcp` with the actual path to where you cloned this repository.

After adding the configuration, restart Claude Desktop. The PHP refactoring tools will be available in your conversations.

#### PHPStorm / IntelliJ IDEA

PHPStorm supports MCP through the AI Assistant plugin:

1. Install the **AI Assistant** plugin from Settings → Plugins
2. Go to Settings → Tools → AI Assistant → Model Context Protocol
3. Click **+** to add a new MCP server
4. Configure:
   - **Name**: PHP Refactor MCP
   - **Command**: `php`
   - **Arguments**: `/absolute/path/to/php-refactor-mcp/bin/php-refactor-mcp`
5. Click **OK** and restart PHPStorm

The refactoring tools will be available through the AI Assistant.

#### Other MCP Clients

For other MCP-compatible clients, use the following server configuration:

- **Command**: `php`
- **Arguments**: `["/path/to/php-refactor-mcp/bin/php-refactor-mcp"]`
- **Transport**: stdio

### Starting the Server Manually

The server uses stdio transport for communication:

```bash
php bin/php-refactor-mcp
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
- `selectionRange` (string): Range in format 'startLine:startColumn-endLine:endColumn' or 'startLine-endLine'
- `methodName` (string): Name for the new method

**Example:**
```json
{
  "file": "/path/to/file.php",
  "selectionRange": "10:5-15:10",
  "methodName": "extractedMethod"
}
```

#### extract_variable

Extract an expression into a named variable.

**Parameters:**
- `file` (string): Path to the PHP file
- `selectionRange` (string): Range in format 'line:column' or 'line'
- `variableName` (string): Name for the new variable (with or without $ prefix)

**Example:**
```json
{
  "file": "/path/to/file.php",
  "selectionRange": "10:15",
  "variableName": "myVar"
}
```

#### introduce_variable

Introduce a new variable from selected expression (preferred for large PHP file refactoring).

**Parameters:**
- `file` (string): Path to the PHP file
- `selectionRange` (string): Range in format 'startLine:startColumn-endLine:endColumn', 'line:column', or 'line'
- `variableName` (string): Name for the new variable (with or without $ prefix)

**Example:**
```json
{
  "file": "/path/to/file.php",
  "selectionRange": "10:15-10:30",
  "variableName": "myVar"
}
```

#### rename_variable

Rename a variable throughout its scope.

**Parameters:**
- `file` (string): Path to the PHP file
- `selectionRange` (string): Range in format 'line:column' or 'line' where variable is used
- `oldName` (string): Current variable name (with or without $ prefix)
- `newName` (string): New variable name (with or without $ prefix)

**Example:**
```json
{
  "file": "/path/to/file.php",
  "selectionRange": "10:5",
  "oldName": "oldVar",
  "newName": "newVar"
}
```

## Development

For information about contributing, setting up the development environment, running tests, and code quality tools, please see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## Author

Gabriel Somoza - [gabriel@somoza.me](mailto:gabriel@somoza.me)
