<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Tools;

use Somoza\PhpRefactorMcp\Tests\Support\FixtureBasedTestCase;
use Somoza\PhpRefactorMcp\Tools\IntroduceVariableTool;

class IntroduceVariableToolTest extends FixtureBasedTestCase
{
    private IntroduceVariableTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new IntroduceVariableTool($this->filesystem);
    }

    // Error cases - traditional test methods

    public function testIntroduceFileNotFound(): void
    {
        $result = $this->tool->introduce('/nonexistent/file.php', '1:1', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('File not found', $result['error']);
    }

    public function testIntroduceEmptyVariableName(): void
    {
        $code = '<?php $x = 1 + 2;';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->introduce($file, '1:12', '');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cannot be empty', $result['error']);
    }

    public function testIntroduceInvalidVariableName(): void
    {
        $code = '<?php $x = 1 + 2;';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->introduce($file, '1:12', '123invalid');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('invalid', $result['error']);
    }

    public function testIntroduceSyntaxError(): void
    {
        $file = $this->createFile('/test.php', '<?php $x = ;');
        $result = $this->tool->introduce($file, '1:12', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testIntroduceInvalidLineNumber(): void
    {
        $code = '<?php
$x = 1 + 2;
';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->introduce($file, '999:1', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Could not find expression', $result['error']);
    }

    public function testIntroduceInvalidRange(): void
    {
        $code = '<?php $x = 1 + 2;';
        $file = $this->createFile('/test.php', $code);
        $result = $this->tool->introduce($file, 'invalid-range', '$var');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid selection range', $result['error']);
    }
}
