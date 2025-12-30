<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\NodeTraverser;
use Somoza\PhpRefactorMcp\Helpers\RefactoringHelpers;
use Somoza\PhpRefactorMcp\Tools\Internal\RenameVariable\ScopeFinder;
use Somoza\PhpRefactorMcp\Tools\Internal\RenameVariable\VariableRenamer;

class RenameVariableTool
{
    /**
     * Rename a variable throughout its scope.
     *
     * @param string $file Path to the PHP file
     * @param string $selectionRange Range in format 'line:column' or 'line'
     * @param string $oldName Current variable name (with or without $ prefix)
     * @param string $newName New variable name (with or without $ prefix)
     *
     * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
     */
    #[McpTool(
        name: 'rename_variable',
        description: 'Rename a variable throughout its scope'
    )]
    public function rename(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file'
        )]
        string $file,
        #[Schema(
            type: 'string',
            description: "Range in format 'line:column' or 'line' where variable is used"
        )]
        string $selectionRange,
        #[Schema(
            type: 'string',
            description: 'Current variable name (with or without $ prefix)'
        )]
        string $oldName,
        #[Schema(
            type: 'string',
            description: 'New variable name (with or without $ prefix)'
        )]
        string $newName
    ): array {
        // Parse the selection range
        if (!RefactoringHelpers::tryParseRange($selectionRange, $line, $column, $endLine, $endColumn)) {
            return [
                'success' => false,
                'error' => "Invalid selection range format. Use 'line:column' or 'line'",
            ];
        }

        // Normalize variable names (remove $ prefix)
        $oldName = ltrim($oldName, '$');
        $newName = ltrim($newName, '$');

        // Validate variable names
        if (empty($oldName) || !preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $oldName)) {
            return [
                'success' => false,
                'error' => 'Old variable name cannot be empty or invalid',
            ];
        }

        if (empty($newName) || !preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $newName)) {
            return [
                'success' => false,
                'error' => 'New variable name cannot be empty or invalid',
            ];
        }

        return RefactoringHelpers::applyFileEdit(
            $file,
            fn($code) => $this->renameVariableInSource($code, $line, $oldName, $newName),
            "Successfully renamed variable '\${$oldName}' to '\${$newName}' in {$file}"
        );
    }

    /**
     * Rename variable in source code.
     *
     * @param string $code Source code
     * @param int $line Line number where the variable is used
     * @param string $oldName Current variable name (without $ prefix)
     * @param string $newName New variable name (without $ prefix)
     *
     * @return string Refactored source code
     */
    private function renameVariableInSource(string $code, int $line, string $oldName, string $newName): string
    {
        // Parse the code
        $ast = RefactoringHelpers::parseCode($code);

        // Find the scope containing the variable
        $scopeFinder = new ScopeFinder($line, $ast);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($scopeFinder);
        $traverser->traverse($ast);

        $scope = $scopeFinder->getScope();
        $isGlobalScope = $scopeFinder->isGlobalScope();

        // Rename the variable within the scope
        $renamer = new VariableRenamer($oldName, $newName, $scope, $isGlobalScope);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($renamer);
        $ast = $traverser->traverse($ast);

        // Generate the modified code
        return RefactoringHelpers::printCode($ast);
    }
}
