<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\RenameVariable;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to find the scope containing a specific line.
 */
class ScopeFinder extends NodeVisitorAbstract
{
    private int $targetLine;
    private ?Node $scope = null;
    /** @var array<Node> */
    private array $scopeStack = [];

    /**
     * @param array<\PhpParser\Node> $ast Not currently used, kept for potential future use
     *
     * @phpstan-ignore-next-line constructor.unusedParam
     */
    public function __construct(int $targetLine, array $ast)
    {
        $this->targetLine = $targetLine;
        // Note: $ast parameter kept for backward compatibility but not used
    }

    public function enterNode(Node $node): ?int
    {
        // Track scope nodes (functions, methods, closures)
        if (
            $node instanceof Node\Stmt\Function_
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
        if (
            $node instanceof Node\Stmt\Function_
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
