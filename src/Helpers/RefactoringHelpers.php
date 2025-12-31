<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Helpers;

use League\Flysystem\FilesystemOperator;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class RefactoringHelpers
{
    /**
     * Apply a refactoring to a file.
     *
     * @param FilesystemOperator $filesystem Filesystem operator
     * @param string $filePath Path to the PHP file
     * @param callable $refactoringFunction Function that takes code and returns refactored code
     * @param string $successMessage Message to return on success
     *
     * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
     */
    public static function applyFileEdit(
        FilesystemOperator $filesystem,
        string $filePath,
        callable $refactoringFunction,
        string $successMessage
    ): array {
        try {
            // Check if file exists
            if (!$filesystem->fileExists($filePath)) {
                return [
                    'success' => false,
                    'error' => "File not found: {$filePath}",
                ];
            }

            // Read file contents - throws UnableToReadFile if not readable
            $code = $filesystem->read($filePath);

            // Apply refactoring
            $refactoredCode = $refactoringFunction($code);

            // Write back to file - throws UnableToWriteFile if not writable
            $filesystem->write($filePath, $refactoredCode);

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
