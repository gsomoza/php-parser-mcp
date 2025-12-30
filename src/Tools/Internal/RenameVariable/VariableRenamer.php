<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\RenameVariable;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to rename variables within a specific scope.
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
            if (
                $node instanceof Node\Stmt\Function_
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
            if (
                $node instanceof Node\Stmt\Function_
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
