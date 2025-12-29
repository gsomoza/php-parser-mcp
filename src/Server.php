<?php

declare(strict_types=1);

namespace Somoza\PhpParserMcp;

use PhpMcp\Server\Server as McpServer;
use PhpMcp\Server\Transports\StdioServerTransport;

class Server
{
    /**
     * Create and configure the MCP server
     */
    public static function create(): McpServer
    {
        $server = McpServer::make()
            ->withServerInfo('php-parser-mcp', '0.1.0')
            ->build();
        
        // Discover MCP tools via attributes
        $server->discover(
            basePath: dirname(__DIR__),
            scanDirs: ['src']
        );
        
        return $server;
    }

    /**
     * Run the MCP server with stdio transport
     */
    public static function run(): void
    {
        $server = self::create();
        $transport = new StdioServerTransport();
        $server->listen($transport);
    }
}
