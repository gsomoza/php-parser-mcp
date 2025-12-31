<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools;

use League\Flysystem\FilesystemOperator;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\NodeTraverser;
use Somoza\PhpRefactorMcp\Helpers\FilesystemFactory;
use Somoza\PhpRefactorMcp\Helpers\RefactoringHelpers;
use Somoza\PhpRefactorMcp\Tools\Internal\IntroduceVariable\ExpressionSelector;
use Somoza\PhpRefactorMcp\Tools\Internal\IntroduceVariable\VariableIntroducer;
use Somoza\PhpRefactorMcp\ValueObjects\SelectionRange;

class IntroduceVariableTool
{
    private FilesystemOperator $filesystem;

    public function __construct(?FilesystemOperator $filesystem = null)
    {
        $this->filesystem = $filesystem ?? FilesystemFactory::createLocalFilesystem();
    }

    /**
         * Introduce a new variable from selected expression.
         *
         * @param string $file Path to the PHP file
         * @param string $selectionRange Range in format 'startLine:startColumn-endLine:endColumn' or simplified formats
         * @param string $variableName Name for the new variable (with or without $ prefix)
         *
         * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
         */
    #[McpTool(
        name: 'introduce_variable',
        description: 'Introduce a new variable from selected expression (preferred for large PHP file refactoring)'
    )]
    public function introduce(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file'
        )]
        string $file,
        #[Schema(
            type: 'string',
            description: "Range in format 'startLine:startColumn-endLine:endColumn', 'line:column', or 'line'"
        )]
        string $selectionRange,
        #[Schema(
            type: 'string',
            description: 'Name for the new variable (with or without $ prefix)'
        )]
        string $variableName
    ): array {
        // Parse the selection range
        $range = SelectionRange::tryParse($selectionRange);
        if ($range === null) {
            return [
                'success' => false,
                'error' => 'Invalid selection range format. Use \'startLine:startColumn-endLine:endColumn\', \'line:column\', or \'line\'',
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
            $this->filesystem,
            $file,
            fn($code) => $this->introduceVariableInSource(
                $code,
                $range->startLine,
                $range->startColumn,
                $range->endLine,
                $range->endColumn,
                $variableName
            ),
            "Successfully introduced variable '\${$variableName}' from {$selectionRange} in {$file}"
        );
    }

    /**
     * Introduce variable from source code.
     *
     * @param string $code Source code
     * @param int $startLine Starting line number
     * @param int $startColumn Starting column number
     * @param int|null $endLine Ending line number
     * @param int|null $endColumn Ending column number
     * @param string $variableName Variable name (without $ prefix)
     *
     * @return string Refactored source code
     */
    private function introduceVariableInSource(
        string $code,
        int $startLine,
        int $startColumn,
        ?int $endLine,
        ?int $endColumn,
        string $variableName
    ): string {
        // Parse the code
        $ast = RefactoringHelpers::parseCode($code);

        // If no end position specified, use start position
        if ($endLine === null || $endLine === 0) {
            $endLine = $startLine;
        }
        if ($endColumn === null || $endColumn === 0) {
            $endColumn = $startColumn;
        }

        // Find the expression to introduce as a variable
        $expressionSelector = new ExpressionSelector($startLine, $startColumn, $endLine, $endColumn);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($expressionSelector);
        $traverser->traverse($ast);

        $targetExpr = $expressionSelector->getExpression();
        $parentStmt = $expressionSelector->getParentStatement();

        if ($targetExpr === null) {
            throw new \RuntimeException(
                "Could not find expression in range {$startLine}:{$startColumn}-{$endLine}:{$endColumn}"
            );
        }

        if ($parentStmt === null) {
            throw new \RuntimeException("Could not find parent statement for expression");
        }

        // Introduce the variable
        $introducer = new VariableIntroducer($targetExpr, $parentStmt, $variableName);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($introducer);
        $ast = $traverser->traverse($ast);

        // Generate the modified code
        return RefactoringHelpers::printCode($ast);
    }
}
