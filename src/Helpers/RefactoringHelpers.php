<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Helpers;

use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class RefactoringHelpers
{
    /**
     * Parse a selection range string in format "startLine:startColumn-endLine:endColumn".
     *
     * @param string $selectionRange Range string
     * @param int $startLine Output parameter for start line
     * @param int $startColumn Output parameter for start column
     * @param int $endLine Output parameter for end line
     * @param int $endColumn Output parameter for end column
     *
     * @return bool True if parsing succeeded
     */
    public static function tryParseRange(
        string $selectionRange,
        ?int &$startLine,
        ?int &$startColumn,
        ?int &$endLine,
        ?int &$endColumn
    ): bool {
        // Format: "startLine:startColumn-endLine:endColumn"
        // Also support simpler formats:
        // - "startLine-endLine" (line range only)
        // - "line:column" (single position)

        if (preg_match('/^(\d+):(\d+)-(\d+):(\d+)$/', $selectionRange, $matches)) {
            // Full format: "startLine:startColumn-endLine:endColumn"
            $startLine = (int) $matches[1];
            $startColumn = (int) $matches[2];
            $endLine = (int) $matches[3];
            $endColumn = (int) $matches[4];
            return true;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $selectionRange, $matches)) {
            // Line range only: "startLine-endLine"
            $startLine = (int) $matches[1];
            $startColumn = 0;
            $endLine = (int) $matches[2];
            $endColumn = 0;
            return true;
        }

        if (preg_match('/^(\d+):(\d+)$/', $selectionRange, $matches)) {
            // Single position: "line:column"
            $startLine = (int) $matches[1];
            $startColumn = (int) $matches[2];
            $endLine = $startLine;
            $endColumn = $startColumn;
            return true;
        }

        if (preg_match('/^(\d+)$/', $selectionRange, $matches)) {
            // Single line: "line"
            $startLine = (int) $matches[1];
            $startColumn = 0;
            $endLine = $startLine;
            $endColumn = 0;
            return true;
        }

        return false;
    }

    /**
     * Apply a refactoring to a file.
     *
     * @param string $filePath Path to the PHP file
     * @param callable $refactoringFunction Function that takes code and returns refactored code
     * @param string $successMessage Message to return on success
     *
     * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
     */
    public static function applyFileEdit(
        string $filePath,
        callable $refactoringFunction,
        string $successMessage
    ): array {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => "File not found: {$filePath}",
                ];
            }

            // Check if file is readable
            if (!is_readable($filePath)) {
                return [
                    'success' => false,
                    'error' => "File is not readable: {$filePath}",
                ];
            }

            // Read file contents
            $code = file_get_contents($filePath);
            if ($code === false) {
                return [
                    'success' => false,
                    'error' => "Failed to read file: {$filePath}",
                ];
            }

            // Apply refactoring
            $refactoredCode = $refactoringFunction($code);

            // Write back to file
            if (file_put_contents($filePath, $refactoredCode) === false) {
                return [
                    'success' => false,
                    'error' => "Failed to write to file: {$filePath}",
                ];
            }

            return [
                'success' => true,
                'code' => $refactoredCode,
                'file' => $filePath,
                'message' => $successMessage,
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'error' => 'Parse error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse PHP code into an AST.
     *
     * @param string $code PHP source code
     *
     * @throws Error If parsing fails
     *
     * @return array<\PhpParser\Node> AST nodes
     */
    public static function parseCode(string $code): array
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            throw new \RuntimeException('Failed to parse code: parser returned null');
        }

        return $ast;
    }

    /**
     * Pretty print AST back to PHP code.
     *
     * @param array<\PhpParser\Node> $ast AST nodes
     *
     * @return string PHP source code
     */
    public static function printCode(array $ast): string
    {
        $printer = new Standard();
        return $printer->prettyPrintFile($ast);
    }
}
