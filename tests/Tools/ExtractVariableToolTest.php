<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Somoza\PhpRefactorMcp\Tools\ExtractVariableTool;

class ExtractVariableToolTest extends TestCase
{
    private ExtractVariableTool $tool;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tool = new ExtractVariableTool();
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

    public function testExtractSimpleExpression(): void
    {
        $code = '<?php
$result = 1 + 2;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '2:11', '$sum');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('code', $result);
        $this->assertStringContainsString('$sum = 1 + 2', $result['code']);
        $this->assertStringContainsString('$result = $sum', $result['code']);
    }

    public function testExtractExpressionWithoutDollarSign(): void
    {
        $code = '<?php
$result = 5 * 10;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '2:11', 'product');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$product = 5 * 10', $result['code']);
        $this->assertStringContainsString('$result = $product', $result['code']);
    }

    public function testExtractExpressionInFunction(): void
    {
        $code = '<?php
function calculate() {
    return 10 + 20;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '3:12', '$sum');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$sum = 10 + 20', $result['code']);
        $this->assertStringContainsString('return $sum', $result['code']);
    }

    public function testExtractExpressionInMethod(): void
    {
        $code = '<?php
class MyClass {
    public function calculate() {
        $x = 5 * 3;
        return $x;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '4:14', '$product');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$product = 5 * 3', $result['code']);
        $this->assertStringContainsString('$x = $product', $result['code']);
    }

    public function testExtractComplexExpression(): void
    {
        $code = '<?php
$total = ($a + $b) * ($c - $d);
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '2:10', '$intermediate');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('code', $result);
        // Should extract an expression and assign it to a variable
        $this->assertStringContainsString('$intermediate', $result['code']);
        $this->assertStringContainsString('$total', $result['code']);
    }

    public function testExtractMethodCall(): void
    {
        $code = '<?php
$result = $obj->method();
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '2:11', '$value');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$value = $obj->method()', $result['code']);
        $this->assertStringContainsString('$result = $value', $result['code']);
    }

    public function testExtractArrayAccess(): void
    {
        $code = '<?php
$value = $array[0];
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '2:10', '$element');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$element = $array[0]', $result['code']);
        $this->assertStringContainsString('$value = $element', $result['code']);
    }

    public function testExtractFileNotFound(): void
    {
        $result = $this->tool->extract('/nonexistent/file.php', '1:1', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('File not found', $result['error']);
    }

    public function testExtractEmptyVariableName(): void
    {
        $code = '<?php $x = 1 + 2;';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '1:12', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testExtractSyntaxError(): void
    {
        $file = $this->createTempFile('<?php $x = ;');
        $result = $this->tool->extract($file, '1:12', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testExtractUnreadableFile(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Skipping file permission test on Windows');
        }

        $file = $this->createTempFile('<?php $x = 1 + 2;');
        chmod($file, 0o000);

        $result = $this->tool->extract($file, '1:12', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not readable', $result['error']);

        // Clean up
        chmod($file, 0o644);
    }

    public function testExtractInvalidLineNumber(): void
    {
        $code = '<?php
$x = 1 + 2;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '999:1', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Could not find expression', $result['error']);
    }
}
