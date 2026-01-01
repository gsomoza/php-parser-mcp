<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Support;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Base test case for fixture-based testing.
 *
 * This class provides infrastructure for discovering and running tests based on fixture files.
 * Fixtures are simple PHP files under tests/Fixtures/{ToolName}/{TestCase}.php
 *
 * Each fixture is a PHP file containing the code to be refactored.
 * Test parameters are parsed from special comment headers in the fixture file.
 *
 * The output is validated using snapshot testing - no custom assertions needed.
 * Error cases should be implemented as regular test methods in the test class.
 */
abstract class FixtureBasedTestCase extends FilesystemTestCase
{
    /**
     * Get the name of the tool being tested.
     * Automatically derives from class name (e.g., RenameVariableToolTest -> RenameVariableTool).
     * Override this method if you need custom tool name derivation.
     */
    protected function getToolName(): string
    {
        $className = static::class;
        $shortName = (new \ReflectionClass($className))->getShortName();

        // Remove 'Test' suffix if present (e.g., RenameVariableToolTest -> RenameVariableTool)
        if (str_ends_with($shortName, 'Test')) {
            return substr($shortName, 0, -4);
        }

        return $shortName;
    }

    /**
     * Execute the tool with the given fixture data.
     *
     * Default implementation that automatically discovers and calls the tool's MCP method.
     * Override this method if you need custom execution logic.
     *
     * @param string $fixtureName The name of the fixture (without .php extension)
     * @param string $code The PHP code from the fixture file
     * @param array<string, mixed> $params Parameters parsed from fixture comments
     *
     * @return array<string, mixed>
     */
    protected function executeTool(string $fixtureName, string $code, array $params): array
    {
        // Create a virtual file with the fixture code
        $file = $this->createFile('/test.php', $code);

        // Get the tool instance from the test class
        $tool = $this->getToolInstance();

        // Find the method with McpTool attribute
        $method = $this->findToolMethod($tool);

        // Get method parameters and map fixture params to method arguments
        $args = $this->mapParamsToMethodArguments($method, $file, $params);

        // Call the tool method
        return $method->invokeArgs($tool, $args);
    }

    /**
     * Get the tool instance being tested.
     * Default implementation looks for a property named 'tool'.
     * Override if your tool instance is stored differently.
     */
    protected function getToolInstance(): object
    {
        $reflection = new \ReflectionClass($this);

        // Try to find a property named 'tool'
        if ($reflection->hasProperty('tool')) {
            $property = $reflection->getProperty('tool');
            $property->setAccessible(true);
            return $property->getValue($this);
        }

        throw new \RuntimeException('Could not find tool instance. Override getToolInstance() or ensure you have a $tool property.');
    }

    /**
     * Find the method with McpTool attribute.
     */
    protected function findToolMethod(object $tool): \ReflectionMethod
    {
        $reflection = new \ReflectionClass($tool);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(\PhpMcp\Server\Attributes\McpTool::class);
            if (!empty($attributes)) {
                return $method;
            }
        }

        throw new \RuntimeException('Could not find method with McpTool attribute in ' . get_class($tool));
    }

    /**
     * Map fixture parameters to method arguments.
     *
     * @param array<string, mixed> $params
     *
     * @return array<int, mixed>
     */
    protected function mapParamsToMethodArguments(\ReflectionMethod $method, string $file, array $params): array
    {
        $args = [];
        $parameters = $method->getParameters();

        foreach ($parameters as $index => $parameter) {
            $paramName = $parameter->getName();

            // First parameter is always the file path
            if ($index === 0 && $paramName === 'file') {
                $args[] = $file;
                continue;
            }

            // Map fixture params to method params by name
            // Common parameter names and their fixture equivalents
            $parameterMap = [
                'selectionRange' => ['line', 'range', 'position', 'selectionRange'],
                'oldName' => ['oldName'],
                'newName' => ['newName'],
                'methodName' => ['methodName'],
                'variableName' => ['variableName'],
            ];

            $value = null;

            // Try to find matching parameter in fixture
            if (isset($parameterMap[$paramName])) {
                foreach ($parameterMap[$paramName] as $fixtureKey) {
                    if (isset($params[$fixtureKey])) {
                        $value = $params[$fixtureKey];
                        break;
                    }
                }
            } elseif (isset($params[$paramName])) {
                $value = $params[$paramName];
            }

            // Use default value if available and no value found
            if ($value === null && $parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            }

            // If still no value, use empty string for required string parameters
            if ($value === null) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === 'string') {
                    $value = '';
                }
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * Data provider that discovers and yields all fixtures for this tool.
     *
     * @return iterable<string, array{fixtureName: string, code: string, params: array<string, mixed>}>
     */
    public static function fixtureProvider(): iterable
    {
        $toolName = static::getToolNameStatic();
        $fixturesDir = __DIR__ . '/../Fixtures/' . $toolName;

        // Create a Flysystem instance for reading fixtures from the real filesystem
        $adapter = new LocalFilesystemAdapter($fixturesDir);
        $filesystem = new Filesystem($adapter);

        // Check if directory exists
        try {
            $listing = $filesystem->listContents('/', false);
        } catch (\Exception $e) {
            // Directory doesn't exist, no fixtures
            return;
        }

        // Find all .php files in the fixtures directory
        foreach ($listing as $item) {
            if ($item->isFile() && str_ends_with($item->path(), '.php')) {
                $fixtureName = basename($item->path(), '.php');

                try {
                    $fixtureContent = $filesystem->read($item->path());
                } catch (\Exception $e) {
                    continue;
                }

                // Parse parameters from comments
                $params = self::parseFixtureParams($fixtureContent);

                yield $fixtureName => [
                    'fixtureName' => $fixtureName,
                    'code' => $fixtureContent,
                    'params' => $params,
                ];
            }
        }
    }

    /**
     * Get tool name statically (for use in data provider).
     */
    protected static function getToolNameStatic(): string
    {
        // Create temporary instance to get tool name
        $reflection = new \ReflectionClass(static::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        return $instance->getToolName();
    }

    /**
     * Parse parameters from fixture file comments.
     *
     * Looks for comment lines like:
     * // param_name: value
     *
     * @return array<string, mixed>
     */
    protected static function parseFixtureParams(string $content): array
    {
        $params = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Match comment lines with parameters: // key: value
            if (preg_match('/^\/\/\s*(\w+):\s*(.+)$/', $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Test a fixture.
     *
     * This is the main test method that will be run for each fixture via the data provider.
     *
     * @dataProvider fixtureProvider
     *
     * @param array<string, mixed> $params
     */
    public function testFixture(string $fixtureName, string $code, array $params): void
    {
        $result = $this->executeTool($fixtureName, $code, $params);

        // All fixtures are expected to succeed - error cases should be regular test methods
        $this->assertTrue($result['success'], 'Expected successful execution for fixture: ' . $fixtureName);
        $this->assertArrayHasKey('code', $result, 'Expected code in result');

        // Use snapshot testing to verify the output
        $this->assertValidPhpSnapshot($result['code']);
    }
}
