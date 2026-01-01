<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use Somoza\PhpRefactorMcp\Tests\Support\FixtureBasedTestCase;
use Somoza\PhpRefactorMcp\Tools\ExtractVariableTool;

class ExtractVariableToolTest extends FixtureBasedTestCase
{
    private ExtractVariableTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new ExtractVariableTool($this->filesystem);
    }

    // Error cases - traditional test methods

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
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->extract($file, '1:12', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testExtractSyntaxError(): void
    {
        $file = $this->createFile('/test.php', '<?php $x = ;');
        $result = $this->tool->extract($file, '1:12', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testExtractInvalidLineNumber(): void
    {
        $code = '<?php
$x = 1 + 2;
';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->extract($file, '999:1', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Could not find expression', $result['error']);
    }
}
