<?php

declare(strict_types=1);

namespace Somoza\PhpParserMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Somoza\PhpParserMcp\Helpers\RefactoringHelpers;

class RenameVariableTool
{
    /**
     * Rename a variable throughout its scope
     *
     * @param string $file Path to the PHP file
     * @param string $selectionRange Range in format 'line:column' or 'line'
     * @param string $oldName Current variable name (with or without $ prefix)
     * @param string $newName New variable name (with or without $ prefix)
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
                'error' => "Invalid selection range format. Use 'line:column' or 'line'"
            ];
        }

        // Normalize variable names (remove $ prefix if present)
        $oldName = ltrim($oldName, '$');
        $newName = ltrim($newName, '$');

        if (empty($oldName) || empty($newName)) {
            return [
                'success' => false,
                'error' => 'Variable names cannot be empty'
            ];
        }

        return RefactoringHelpers::applyFileEdit(
            $file,
            fn($code) => $this->renameVariableInSource($code, $line, $oldName, $newName),
            "Successfully renamed variable '\${$oldName}' to '\${$newName}' at {$selectionRange} in {$file}"
        );
    }

    /**
     * Rename variable in source code
     *
     * @param string $code Source code
     * @param int $line Line number
     * @param string $oldName Old variable name
     * @param string $newName New variable name
     * @return string Refactored source code
     */
    private function renameVariableInSource(string $code, int $line, string $oldName, string $newName): string
    {
        // Parse the code
        $ast = RefactoringHelpers::parseCode($code);

        // Find the scope containing the variable at the specified line
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

/**
 * NodeVisitor to find the scope containing a specific line
 */
class ScopeFinder extends NodeVisitorAbstract
{
    private int $targetLine;
    private ?Node $scope = null;
    private array $scopeStack = [];
    private array $ast;

    public function __construct(int $targetLine, array $ast)
    {
        $this->targetLine = $targetLine;
        $this->ast = $ast;
    }

    public function enterNode(Node $node): ?int
    {
        // Track scope nodes (functions, methods, closures)
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Expr\ArrowFunction
        ) {
            $this->scopeStack[] = $node;
        }

        // Check if this node contains the target line
        if ($node->hasAttribute('startLine') && $node->hasAttribute('endLine')) {
            $startLine = $node->getAttribute('startLine');
            $endLine = $node->getAttribute('endLine');

            if ($startLine <= $this->targetLine && $this->targetLine <= $endLine) {
                // If we're in a scope, use the innermost one
                if (!empty($this->scopeStack)) {
                    $this->scope = end($this->scopeStack);
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // Pop scope when leaving scope nodes
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Expr\ArrowFunction
        ) {
            if (!empty($this->scopeStack) && end($this->scopeStack) === $node) {
                array_pop($this->scopeStack);
            }
        }

        return null;
    }

    public function getScope(): ?Node
    {
        // Return null to indicate global scope
        return $this->scope;
    }

    public function isGlobalScope(): bool
    {
        return $this->scope === null;
    }
}

/**
 * NodeVisitor to rename variables within a specific scope
 */
class VariableRenamer extends NodeVisitorAbstract
{
    private string $oldName;
    private string $newName;
    private ?Node $targetScope;
    private bool $isGlobalScope;
    private bool $inTargetScope = false;
    private int $scopeDepth = 0;

    public function __construct(string $oldName, string $newName, ?Node $targetScope, bool $isGlobalScope)
    {
        $this->oldName = $oldName;
        $this->newName = $newName;
        $this->targetScope = $targetScope;
        $this->isGlobalScope = $isGlobalScope;
        
        // If target scope is global, start in scope
        if ($isGlobalScope) {
            $this->inTargetScope = true;
        }
    }

    public function enterNode(Node $node): ?int
    {
        // Check if we're entering the target scope
        if (!$this->isGlobalScope && $node === $this->targetScope) {
            $this->inTargetScope = true;
        }

        // Track entering nested scopes when in global scope
        if ($this->isGlobalScope) {
            if ($node instanceof Node\Stmt\Function_
                || $node instanceof Node\Stmt\ClassMethod
                || $node instanceof Node\Expr\Closure
                || $node instanceof Node\Expr\ArrowFunction
            ) {
                $this->scopeDepth++;
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        // For global scope, don't rename inside functions/methods/closures
        if ($this->isGlobalScope) {
            if ($node instanceof Node\Stmt\Function_
                || $node instanceof Node\Stmt\ClassMethod
                || $node instanceof Node\Expr\Closure
                || $node instanceof Node\Expr\ArrowFunction
            ) {
                $this->scopeDepth--;
            }

            // Only rename if we're at depth 0 (global scope)
            if ($this->scopeDepth === 0 && $node instanceof Variable) {
                if (is_string($node->name) && $node->name === $this->oldName) {
                    $node->name = $this->newName;
                }
            }
        } else {
            // Rename variable if we're in the target scope
            if ($this->inTargetScope && $node instanceof Variable) {
                if (is_string($node->name) && $node->name === $this->oldName) {
                    $node->name = $this->newName;
                }
            }

            // Check if we're leaving the target scope
            if ($node === $this->targetScope) {
                $this->inTargetScope = false;
            }
        }

        return null;
    }
}
