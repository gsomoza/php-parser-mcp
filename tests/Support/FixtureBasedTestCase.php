<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tests\Support;

use DirectoryIterator;

/**
 * Base test case for fixture-based testing.
 *
 * This class provides infrastructure for discovering and running tests based on fixture files.
 * Fixtures are organized in directories under tests/Fixtures/{ToolName}/{TestCaseName}/
 *
 * Each test case directory should contain:
 * - input.php: The PHP code to test (optional for error cases)
 * - test.json: Metadata about the test (parameters, expected success/failure)
 */
abstract class FixtureBasedTestCase extends FilesystemTestCase
{
    /**
     * Get the name of the tool being tested.
     * This should match the directory name in tests/Fixtures/
     */
    abstract protected function getToolName(): string;

    /**
     * Execute the tool with the given fixture data.
     *
     * @param array<string, mixed> $fixture
     * @return array<string, mixed>
     */
    abstract protected function executeTool(array $fixture): array;

    /**
     * Data provider that discovers and yields all fixtures for this tool.
     *
     * @return iterable<string, array{fixture: array<string, mixed>}>
     */
    public static function fixtureProvider(): iterable
    {
        $toolName = static::getToolNameStatic();
        $fixturesDir = __DIR__ . '/../Fixtures/' . $toolName;

        if (!is_dir($fixturesDir)) {
            return;
        }

        foreach (new DirectoryIterator($fixturesDir) as $testCaseDir) {
            if ($testCaseDir->isDot() || !$testCaseDir->isDir()) {
                continue;
            }

            $testCaseName = $testCaseDir->getFilename();
            $fixture = self::loadFixture($fixturesDir . '/' . $testCaseName);

            if ($fixture !== null) {
                yield $testCaseName => ['fixture' => $fixture];
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
     * Load a fixture from a directory.
     *
     * @return array<string, mixed>|null
     */
    protected static function loadFixture(string $fixtureDir): ?array
    {
        $testJsonPath = $fixtureDir . '/test.json';
        $inputPhpPath = $fixtureDir . '/input.php';

        if (!file_exists($testJsonPath)) {
            return null;
        }

        $testData = json_decode(file_get_contents($testJsonPath), true);
        if (!is_array($testData)) {
            return null;
        }

        // Load input.php if it exists
        $inputCode = null;
        if (file_exists($inputPhpPath)) {
            $inputCode = file_get_contents($inputPhpPath);
        }

        return [
            'name' => basename($fixtureDir),
            'input' => $inputCode,
            'params' => $testData['params'] ?? [],
            'expectSuccess' => $testData['expectSuccess'] ?? true,
            'expectError' => $testData['expectError'] ?? null,
        ];
    }

    /**
     * Test a fixture.
     *
     * This is the main test method that will be run for each fixture via the data provider.
     *
     * @dataProvider fixtureProvider
     * @param array<string, mixed> $fixture
     */
    public function testFixture(array $fixture): void
    {
        $result = $this->executeTool($fixture);

        // Check success/failure expectation
        if ($fixture['expectSuccess']) {
            $this->assertTrue($result['success'], 'Expected successful execution');
            $this->assertArrayHasKey('code', $result, 'Expected code in result');

            // Use snapshot testing to verify the output
            $this->assertValidPhpSnapshot($result['code']);
        } else {
            $this->assertFalse($result['success'], 'Expected failed execution');
            $this->assertArrayHasKey('error', $result, 'Expected error in result');

            // If specific error message is expected, check it
            if ($fixture['expectError'] !== null) {
                $this->assertStringContainsString(
                    $fixture['expectError'],
                    $result['error'],
                    'Error message does not match expected'
                );
            }
        }
    }
}
