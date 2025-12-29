<?php

declare(strict_types=1);

/**
 * Example: Using ParseTool directly
 * 
 * This demonstrates how to use the ParseTool class directly
 * without going through the MCP server protocol.
 */

require __DIR__ . '/../vendor/autoload.php';

use Somoza\PhpParserMcp\Tools\ParseTool;

// Create an instance of the ParseTool
$tool = new ParseTool();

// Create temporary test files
$tempDir = sys_get_temp_dir() . '/php-parser-mcp-examples';
if (!is_dir($tempDir)) {
    mkdir($tempDir);
}

// Example 1: Parse simple PHP code
echo "=== Example 1: Simple PHP Code ===\n";
$file1 = $tempDir . '/example1.php';
file_put_contents($file1, '<?php $x = 1 + 2;');
$result1 = $tool->parse($file1);
echo "Success: " . ($result1['success'] ? 'Yes' : 'No') . "\n";
echo "File: " . ($result1['file'] ?? 'N/A') . "\n";
echo "Node Count: " . ($result1['nodeCount'] ?? 'N/A') . "\n";
echo "AST:\n" . ($result1['ast'] ?? 'N/A') . "\n\n";

// Example 2: Parse a class definition
echo "=== Example 2: Class Definition ===\n";
$file2 = $tempDir . '/example2.php';
file_put_contents($file2, '<?php
class Calculator {
    private int $result = 0;
    
    public function add(int $a, int $b): int {
        $this->result = $a + $b;
        return $this->result;
    }
}');
$result2 = $tool->parse($file2);
echo "Success: " . ($result2['success'] ? 'Yes' : 'No') . "\n";
echo "File: " . ($result2['file'] ?? 'N/A') . "\n";
echo "Node Count: " . ($result2['nodeCount'] ?? 'N/A') . "\n";
echo "AST:\n" . ($result2['ast'] ?? 'N/A') . "\n\n";

// Example 3: Parse with syntax error
echo "=== Example 3: Syntax Error ===\n";
$file3 = $tempDir . '/example3.php';
file_put_contents($file3, '<?php $x = ;'); // Missing expression
$result3 = $tool->parse($file3);
echo "Success: " . ($result3['success'] ? 'Yes' : 'No') . "\n";
echo "Error: " . ($result3['error'] ?? 'N/A') . "\n\n";

// Example 4: Parse PHP 7.1 features
echo "=== Example 4: PHP 7.1 Features ===\n";
$file4 = $tempDir . '/example4.php';
file_put_contents($file4, '<?php
function process(?string $input): ?array {
    if ($input === null) {
        return null;
    }
    
    [$first, $second] = explode(",", $input);
    return [$first, $second];
}');
$result4 = $tool->parse($file4);
echo "Success: " . ($result4['success'] ? 'Yes' : 'No') . "\n";
echo "File: " . ($result4['file'] ?? 'N/A') . "\n";
echo "Node Count: " . ($result4['nodeCount'] ?? 'N/A') . "\n";
echo "AST:\n" . ($result4['ast'] ?? 'N/A') . "\n\n";

// Example 5: Parse non-existent file
echo "=== Example 5: Non-existent File ===\n";
$result5 = $tool->parse('/path/to/nonexistent/file.php');
echo "Success: " . ($result5['success'] ? 'Yes' : 'No') . "\n";
echo "Error: " . ($result5['error'] ?? 'N/A') . "\n\n";

// Clean up
foreach (glob($tempDir . '/*.php') ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
rmdir($tempDir);

echo "Examples completed. Temporary files cleaned up.\n";
