<?php

declare(strict_types=1);

namespace Somoza\PhpParserMcp\Tests\Tools;

use Somoza\PhpParserMcp\Tools\RenameVariableTool;
use PHPUnit\Framework\TestCase;

class RenameVariableToolTest extends TestCase
{
    private RenameVariableTool $tool;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tool = new RenameVariableTool();
        $this->tempDir = sys_get_temp_dir() . '/php-parser-mcp-test-' . uniqid();
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

    public function testRenameVariableInFunction(): void
    {
        $code = '<?php
function test() {
    $oldVar = 1;
    $result = $oldVar + 2;
    return $result;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '3', '$oldVar', '$newVar');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('code', $result);
        $this->assertStringContainsString('$newVar = 1', $result['code']);
        $this->assertStringContainsString('$result = $newVar + 2', $result['code']);
        $this->assertStringNotContainsString('$oldVar', $result['code']);
    }

    public function testRenameVariableWithoutDollarSign(): void
    {
        $code = '<?php
function test() {
    $oldVar = 1;
    return $oldVar;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '3', 'oldVar', 'newVar');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$newVar = 1', $result['code']);
        $this->assertStringNotContainsString('$oldVar', $result['code']);
    }

    public function testRenameVariableInMethod(): void
    {
        $code = '<?php
class MyClass {
    public function myMethod() {
        $temp = 5;
        $result = $temp * 2;
        return $result;
    }
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '4', '$temp', '$value');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$value = 5', $result['code']);
        $this->assertStringContainsString('$result = $value * 2', $result['code']);
        $this->assertStringNotContainsString('$temp', $result['code']);
    }

    public function testRenameVariableInClosure(): void
    {
        $code = '<?php
$closure = function() {
    $x = 10;
    return $x * 2;
};';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '3', '$x', '$num');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$num = 10', $result['code']);
        $this->assertStringContainsString('return $num * 2', $result['code']);
    }

    public function testRenameVariableDoesNotAffectOtherScopes(): void
    {
        $code = '<?php
function foo() {
    $var = 1;
    return $var;
}

function bar() {
    $var = 2;
    return $var;
}';
        $file = $this->createTempFile($code);
        // Rename in first function only
        $result = $this->tool->rename($file, '3', '$var', '$value');

        $this->assertTrue($result['success']);
        // First function should have $value
        $this->assertStringContainsString('$value = 1', $result['code']);
        $this->assertStringContainsString('return $value', $result['code']);
        // Second function should still have $var
        $this->assertStringContainsString('$var = 2', $result['code']);
    }

    public function testRenameVariableMultipleOccurrences(): void
    {
        $code = '<?php
function calculate() {
    $sum = 0;
    $sum = $sum + 1;
    $sum = $sum + 2;
    $sum = $sum + 3;
    return $sum;
}';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '3', '$sum', '$total');

        $this->assertTrue($result['success']);
        $this->assertStringNotContainsString('$sum', $result['code']);
        // All occurrences should be renamed
        $count = substr_count($result['code'], '$total');
        $this->assertGreaterThanOrEqual(5, $count); // At least 5 occurrences
    }

    public function testRenameVariableInGlobalScope(): void
    {
        $code = '<?php
$globalVar = 100;
echo $globalVar;
';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '2', '$globalVar', '$config');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('$config = 100', $result['code']);
        $this->assertStringContainsString('echo $config', $result['code']);
    }

    public function testRenameVariableFileNotFound(): void
    {
        $result = $this->tool->rename('/nonexistent/file.php', '1', '$old', '$new');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('File not found', $result['error']);
    }

    public function testRenameVariableEmptyOldName(): void
    {
        $code = '<?php $x = 1;';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '1', '', '$new');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testRenameVariableEmptyNewName(): void
    {
        $code = '<?php $x = 1;';
        $file = $this->createTempFile($code);
        $result = $this->tool->rename($file, '1', '$old', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testRenameVariableSyntaxError(): void
    {
        $file = $this->createTempFile('<?php $x = ;');
        $result = $this->tool->rename($file, '1', '$x', '$y');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testRenameVariableUnreadableFile(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Skipping file permission test on Windows');
        }

        $file = $this->createTempFile('<?php $x = 1;');
        chmod($file, 0000);

        $result = $this->tool->rename($file, '1', '$x', '$y');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not readable', $result['error']);

        // Clean up
        chmod($file, 0644);
    }
}
