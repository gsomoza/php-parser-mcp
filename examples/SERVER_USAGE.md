# Example: Starting the MCP Server

## Using the Binary

The simplest way to start the MCP server is using the provided binary:

```bash
php bin/php-refactor-mcp
```

The server will listen on stdio and wait for MCP protocol messages.

## Using from Code

You can also start the server programmatically:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Somoza\PhpRefactorMcp\Server;

// Start the MCP server with stdio transport
Server::run();
```

## Testing with MCP Inspector

To test the server interactively, you can use the [MCP Inspector](https://github.com/modelcontextprotocol/inspector):

```bash
# Install MCP Inspector
npm install -g @modelcontextprotocol/inspector

# Run the inspector with our server
mcp-inspector php bin/php-refactor-mcp
```

## Example MCP Protocol Messages

### Initialize Request
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2024-11-05",
    "capabilities": {},
    "clientInfo": {
      "name": "test-client",
      "version": "1.0.0"
    }
  }
}
```

### List Tools Request
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list",
  "params": {}
}
```

### Call Parse Tool
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "parse_php",
    "arguments": {
      "file": "/path/to/file.php"
    }
  }
}
```

## Using with Claude Desktop

Add this configuration to your Claude Desktop config file:

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "php-parser": {
      "command": "php",
      "args": ["/path/to/php-refactor-mcp/bin/php-refactor-mcp"]
    }
  }
}
```

After restarting Claude Desktop, the PHP parser tools will be available.
