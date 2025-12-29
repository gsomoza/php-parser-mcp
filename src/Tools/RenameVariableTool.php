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

class RenameVariableTool
{
    private ParserFactory $parserFactory;
    private Standard $printer;

    public function __construct()
    {
        $this->parserFactory = new ParserFactory();
        $this->printer = new Standard();
    }

    /**
     * Rename a variable throughout its scope
     *
     * @param string $file Path to the PHP file
     * @param int $line Line number where variable is used
     * @param string $oldName Current variable name (with or without $ prefix)
     * @param string $newName New variable name (with or without $ prefix)
     * @return array{success: bool, code?: string, file?: string, error?: string}
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
            type: 'integer',
            description: 'Line number where variable is used'
        )]
        int $line,
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
        try {
            // Check if file exists
            if (!file_exists($file)) {
                return [
                    'success' => false,
                    'error' => "File not found: {$file}"
                ];
            }

            // Check if file is readable
            if (!is_readable($file)) {
                return [
                    'success' => false,
                    'error' => "File is not readable: {$file}"
                ];
            }

            // Read file contents
            $code = file_get_contents($file);
            if ($code === false) {
                return [
                    'success' => false,
                    'error' => "Failed to read file: {$file}"
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

            // Parse the code
            $parser = $this->parserFactory->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse code: parser returned null'
                ];
            }

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
            $modifiedCode = $this->printer->prettyPrintFile($ast);

            return [
                'success' => true,
                'code' => $modifiedCode,
                'file' => $file
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'error' => 'Parse error: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
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
