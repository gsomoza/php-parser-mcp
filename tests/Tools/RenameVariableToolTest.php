<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use Somoza\PhpRefactorMcp\Tests\Support\FixtureBasedTestCase;
use Somoza\PhpRefactorMcp\Tools\RenameVariableTool;

class RenameVariableToolTest extends FixtureBasedTestCase
{
    private RenameVariableTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new RenameVariableTool($this->filesystem);
    }

    // Error cases - traditional test methods

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
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->rename($file, '1', '', '$new');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testRenameVariableEmptyNewName(): void
    {
        $code = '<?php $x = 1;';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->rename($file, '1', '$old', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testRenameVariableSyntaxError(): void
    {
        $file = $this->createFile('/test.php', '<?php $x = ;');
        $result = $this->tool->rename($file, '1', '$x', '$y');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }
}
