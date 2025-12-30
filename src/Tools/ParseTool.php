<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\Error;
use PhpParser\ParserFactory;

class ParseTool
{
    private ParserFactory $parserFactory;

    public function __construct()
    {
        $this->parserFactory = new ParserFactory();
    }

    /**
     * Parse a PHP file and return the Abstract Syntax Tree (AST) representation.
     *
     * @param string $file Path to the PHP file to parse
     *
     * @return array{success: bool, ast?: string, nodeCount?: int, file?: string, error?: string}
     */
    #[McpTool(
        name: 'parse_php',
        description: 'Parse a PHP file and return the Abstract Syntax Tree (AST) representation'
    )]
    public function parse(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file to parse'
        )]
        string $file
    ): array {
        try {
            // Check if file exists
            if (!file_exists($file)) {
                return [
                    'success' => false,
                    'error' => "File not found: {$file}",
                ];
            }

            // Check if file is readable
            if (!is_readable($file)) {
                return [
                    'success' => false,
                    'error' => "File is not readable: {$file}",
                ];
            }

            // Read file contents
            $code = file_get_contents($file);
            if ($code === false) {
                return [
                    'success' => false,
                    'error' => "Failed to read file: {$file}",
                ];
            }

            $parser = $this->parserFactory->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse code: parser returned null',
                ];
            }

            // Convert AST to readable string representation
            $astString = $this->astToString($ast);

            return [
                'success' => true,
                'ast' => $astString,
                'nodeCount' => count($ast),
                'file' => $file,
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'error' => 'Parse error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert AST nodes to a readable string representation.
     *
     * @param array<\PhpParser\Node> $nodes
     */
    private function astToString(array $nodes): string
    {
        $output = [];
        foreach ($nodes as $node) {
            $output[] = $this->nodeToString($node, 0);
        }
        return implode("\n", $output);
    }

    /**
     * Convert a single node to string with indentation, including child nodes.
     */
    private function nodeToString(object $node, int $depth): string
    {
        if ($depth > 10) { // Prevent infinite recursion
            return str_repeat('  ', $depth) . '...';
        }

        $indent = str_repeat('  ', $depth);
        $className = get_class($node);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        $result = $indent . $shortName;

        // Add some useful properties for common node types
        if (property_exists($node, 'name')) {
            if (is_object($node->name) && method_exists($node->name, '__toString')) {
                $result .= ' (' . $node->name . ')';
            } elseif (is_string($node->name)) {
                $result .= ' (' . $node->name . ')';
            }
        }

        // Recursively process child nodes
        $children = [];
        foreach (get_object_vars($node) as $property => $value) {
            if ($property === 'attributes') {
                continue; // Skip internal attributes
            }

            if (is_array($value)) {
                foreach ($value as $child) {
                    if ($this->isPhpParserNode($child)) {
                        $children[] = $this->nodeToString($child, $depth + 1);
                    }
                }
            } elseif ($this->isPhpParserNode($value)) {
                $children[] = $this->nodeToString($value, $depth + 1);
            }
        }

        if (!empty($children)) {
            $result .= "\n" . implode("\n", $children);
        }

        return $result;
    }

    /**
     * Check if a value is a PhpParser node object.
     */
    private function isPhpParserNode(mixed $value): bool
    {
        return is_object($value) && str_starts_with(get_class($value), 'PhpParser\\Node\\');
    }
}
