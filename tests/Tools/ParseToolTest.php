<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Somoza\PhpRefactorMcp\Tools\ParseTool;

class ParseToolTest extends TestCase
{
    private ParseTool $tool;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tool = new ParseTool();
        $this->tempDir = sys_get_temp_dir() . '/php-refactor-mcp-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    } elseif (is_dir($file)) {
                        rmdir($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    private function createTempFile(string $content): string
    {
        $file = $this->tempDir . '/test_' . uniqid() . '.php';
        file_put_contents($file, $content);
        return $file;
    }

    public function testParseSimpleCode(): void
    {
        $file = $this->createTempFile('<?php $x = 1 + 2;');
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertArrayHasKey('nodeCount', $result);
        $this->assertGreaterThan(0, $result['nodeCount']);
        $this->assertEquals($file, $result['file']);
    }

    public function testParseClassDefinition(): void
    {
        $code = '<?php
class MyClass {
    private $property;

    public function myMethod() {
        return $this->property;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertStringContainsString('Class_', $result['ast']);
    }

    public function testParseFunctionDefinition(): void
    {
        $code = '<?php
function calculateSum($a, $b) {
    return $a + $b;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertStringContainsString('Function_', $result['ast']);
    }

    public function testParsePhp71Code(): void
    {
        // PHP 7.1 features: nullable types, list destructuring
        $code = '<?php
function test(?string $param): ?int {
    [$a, $b] = [1, 2];
    return $a + $b;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }

    public function testParseSyntaxError(): void
    {
        $file = $this->createTempFile('<?php $x = ;'); // Syntax error: missing expression
        $result = $this->tool->parse($file);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testParseInvalidCode(): void
    {
        $file = $this->createTempFile('not valid php code at all');
        $result = $this->tool->parse($file);

        // Text without PHP tags is treated as InlineHTML, which is valid
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }

    public function testParseEmptyCode(): void
    {
        $file = $this->createTempFile('');
        $result = $this->tool->parse($file);

        // Empty code results in empty AST (no statements)
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('nodeCount', $result);
        $this->assertEquals(0, $result['nodeCount']);
    }

    public function testParseVariableAssignment(): void
    {
        $code = '<?php
$name = "John";
$age = 30;
$isActive = true;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertArrayHasKey('nodeCount', $result);
        $this->assertEquals(3, $result['nodeCount']);
    }

    public function testParseNamespaceAndUseStatements(): void
    {
        $code = '<?php
namespace MyApp\Services;

use MyApp\Models\User;
use MyApp\Repositories\UserRepository;

class UserService {
    private UserRepository $repository;
}
';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertStringContainsString('Namespace_', $result['ast']);
    }

    public function testParseComplexExpression(): void
    {
        $code = '<?php
$result = ($a + $b) * ($c - $d) / $e;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->parse($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }

    public function testParseNonExistentFile(): void
    {
        $result = $this->tool->parse('/path/to/nonexistent/file.php');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('File not found', $result['error']);
    }

    public function testParseUnreadableFile(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Skipping file permission test on Windows');
        }

        $file = $this->createTempFile('<?php echo "test";');
        chmod($file, 0o000);

        $result = $this->tool->parse($file);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not readable', $result['error']);

        // Clean up
        chmod($file, 0o644);
    }
}
