<?php

declare(strict_types=1);

namespace Somoza\PhpParserMcp\Tests\Tools;

use Somoza\PhpParserMcp\Tools\ParseTool;
use PHPUnit\Framework\TestCase;

class ParseToolTest extends TestCase
{
    private ParseTool $tool;

    protected function setUp(): void
    {
        $this->tool = new ParseTool();
    }

    public function testParseSimpleCode(): void
    {
        $code = '<?php $x = 1 + 2;';
        $result = $this->tool->parse($code);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertArrayHasKey('nodeCount', $result);
        $this->assertGreaterThan(0, $result['nodeCount']);
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
        $result = $this->tool->parse($code);

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
        $result = $this->tool->parse($code);

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
        $result = $this->tool->parse($code);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }

    public function testParseSyntaxError(): void
    {
        $code = '<?php $x = ;'; // Syntax error: missing expression
        $result = $this->tool->parse($code);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parse error', $result['error']);
    }

    public function testParseInvalidCode(): void
    {
        $code = 'not valid php code at all';
        $result = $this->tool->parse($code);

        // Text without PHP tags is treated as InlineHTML, which is valid
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }

    public function testParseEmptyCode(): void
    {
        $code = '';
        $result = $this->tool->parse($code);

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
        $result = $this->tool->parse($code);

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
        $result = $this->tool->parse($code);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
        $this->assertStringContainsString('Namespace_', $result['ast']);
    }

    public function testParseComplexExpression(): void
    {
        $code = '<?php
$result = ($a + $b) * ($c - $d) / $e;
';
        $result = $this->tool->parse($code);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ast', $result);
    }
}
