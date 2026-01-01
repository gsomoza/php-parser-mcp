<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use Somoza\PhpRefactorMcp\Tests\Support\FixtureBasedTestCase;
use Somoza\PhpRefactorMcp\Tools\ExtractMethodTool;

class ExtractMethodToolTest extends FixtureBasedTestCase
{
    private ExtractMethodTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new ExtractMethodTool($this->filesystem);
    }

    // Error cases - traditional test methods

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
        $file = $this->createFile('/test.php', $code);
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
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->extract($file, '5-3', 'method');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('must be less than or equal to', $result['error']);
    }

    public function testExtractMethodSyntaxError(): void
    {
        $file = $this->createFile('/test.php', '<?php class Test { public function test() { $x = ; } }');
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
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->extract($file, '3-4', 'add');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('within a class', $result['error']);
    }
}
