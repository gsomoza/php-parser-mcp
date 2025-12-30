<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Somoza\PhpRefactorMcp\Tools\ExtractMethodTool;

class ExtractMethodToolTest extends TestCase
{
    private ExtractMethodTool $tool;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tool = new ExtractMethodTool();
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

    public function testExtractSimpleMethod(): void
    {
        $code = '<?php
class Calculator {
    public function calculate() {
        $a = 1;
        $b = 2;
        $sum = $a + $b;
        return $sum;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '6-6', 'calculateSum');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('code', $result);
        $this->assertStringContainsString('private function calculateSum', $result['code']);
        $this->assertStringContainsString('$this->calculateSum', $result['code']);
    }

    public function testExtractMethodWithParameters(): void
    {
        $code = '<?php
class Calculator {
    public function calculate() {
        $x = 10;
        $y = 20;
        $result = $x + $y;
        echo $result;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '6-6', 'add');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('private function add', $result['code']);
        // Should have parameters $x and $y
        $this->assertStringContainsString('$x', $result['code']);
        $this->assertStringContainsString('$y', $result['code']);
    }

    public function testExtractMethodWithReturnValue(): void
    {
        $code = '<?php
class Calculator {
    public function calculate() {
        $x = 5;
        $result = $x * 2;
        return $result;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '5-5', 'double');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('private function double', $result['code']);
        $this->assertStringContainsString('return $result', $result['code']);
        $this->assertStringContainsString('$result = $this->double', $result['code']);
    }

    public function testExtractMultipleStatements(): void
    {
        $code = '<?php
class Calculator {
    public function calculate() {
        $a = 1;
        $b = 2;
        $c = 3;
        $sum = $a + $b + $c;
        return $sum;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '6-7', 'calculateSum');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('private function calculateSum', $result['code']);
        $this->assertStringContainsString('$sum = $a + $b + $c', $result['code']);
    }

    public function testExtractMethodFileNotFound(): void
    {
        $result = $this->tool->extract('/nonexistent/file.php', '1-2', 'method');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('File not found', $result['error']);
    }

    public function testExtractMethodEmptyName(): void
    {
        $code = '<?php
class Test {
    public function test() {
        $x = 1;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '4-4', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testExtractMethodInvalidRange(): void
    {
        $code = '<?php
class Test {
    public function test() {
        $x = 1;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '5-3', 'method');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('must be less than or equal to', $result['error']);
    }

    public function testExtractMethodSyntaxError(): void
    {
        $file = $this->createTempFile('<?php class Test { public function test() { $x = ; } }');
        $result = $this->tool->extract($file, '1-1', 'method');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testExtractMethodOutsideClass(): void
    {
        $code = '<?php
function globalFunction() {
    $x = 1;
    $y = 2;
    return $x + $y;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->extract($file, '3-4', 'add');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('within a class', $result['error']);
    }

    public function testExtractMethodUnreadableFile(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Skipping file permission test on Windows');
        }

        $file = $this->createTempFile('<?php class Test { function test() { $x = 1; } }');
        chmod($file, 0o000);

        $result = $this->tool->extract($file, '1-1', 'method');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not readable', $result['error']);

        // Clean up
        chmod($file, 0o644);
    }
}
