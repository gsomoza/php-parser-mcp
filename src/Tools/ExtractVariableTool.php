<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\NodeTraverser;
use Somoza\PhpRefactorMcp\Helpers\RefactoringHelpers;
use Somoza\PhpRefactorMcp\Tools\Internal\ExtractVariable\ExpressionExtractor;
use Somoza\PhpRefactorMcp\Tools\Internal\ExtractVariable\ExpressionFinder;

class ExtractVariableTool
{
    /**
     * Extract an expression into a named variable.
     *
     * @param string $file Path to the PHP file
     * @param string $selectionRange Range in format 'line:column' or 'line'
     * @param string $variableName Name for the new variable (with or without $ prefix)
     *
     * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
     */
    #[McpTool(
        name: 'extract_variable',
        description: 'Extract an expression into a named variable'
    )]
    public function extract(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file'
        )]
        string $file,
        #[Schema(
            type: 'string',
            description: "Range in format 'line:column' or 'line'"
        )]
        string $selectionRange,
        #[Schema(
            type: 'string',
            description: 'Name for the new variable (with or without $ prefix)'
        )]
        string $variableName
    ): array {
        // Parse the selection range (line and optional column)
        if (!RefactoringHelpers::tryParseRange($selectionRange, $line, $column, $endLine, $endColumn)) {
            return [
                'success' => false,
                'error' => "Invalid selection range format. Use 'line:column' or 'line'",
            ];
        }

        // Normalize variable name (add $ if missing)
        if (!str_starts_with($variableName, '$')) {
            $variableName = '$' . $variableName;
        }

        // Remove $ prefix for internal use
        $variableName = ltrim($variableName, '$');

        // Validate variable name
        if (empty($variableName) || !preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $variableName)) {
            return [
                'success' => false,
                'error' => 'Variable name cannot be empty or invalid',
            ];
        }

        return RefactoringHelpers::applyFileEdit(
            $file,
            fn($code) => $this->extractVariableInSource($code, $line, $column ?? 0, $variableName),
            "Successfully extracted variable '\${$variableName}' at line {$line} in {$file}"
        );
    }

    /**
     * Extract variable from source code.
     *
     * @param string $code Source code
     * @param int $line Line number
     * @param int $column Column number
     * @param string $variableName Variable name (without $ prefix)
     *
     * @return string Refactored source code
     */
    private function extractVariableInSource(string $code, int $line, int $column, string $variableName): string
    {
        // Parse the code
        $ast = RefactoringHelpers::parseCode($code);

        // Find the expression to extract
        $expressionFinder = new ExpressionFinder($line, $column);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($expressionFinder);
        $traverser->traverse($ast);

        $targetExpr = $expressionFinder->getExpression();
        $parentStmt = $expressionFinder->getParentStatement();

        if ($targetExpr === null) {
            throw new \RuntimeException("Could not find expression at line {$line}, column {$column}");
        }

        if ($parentStmt === null) {
            throw new \RuntimeException("Could not find parent statement for expression");
        }

        // Extract the expression
        $extractor = new ExpressionExtractor($targetExpr, $parentStmt, $variableName);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($extractor);
        $ast = $traverser->traverse($ast);

        // Generate the modified code
        return RefactoringHelpers::printCode($ast);
    }
}
