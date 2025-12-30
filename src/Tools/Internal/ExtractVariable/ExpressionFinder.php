<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\ExtractVariable;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to find an expression at a specific line and column.
 */
class ExpressionFinder extends NodeVisitorAbstract
{
    private int $targetLine;
    private ?Node $parentStatement = null;
    /** @var array<Node\Stmt> */
    private array $stmtStack = [];
    private ?Expr $bestMatch = null;

    /**
     * @param int $targetColumn Not currently used, kept for potential future use
     *
     * @phpstan-ignore-next-line constructor.unusedParam
     */
    public function __construct(int $targetLine, int $targetColumn)
    {
        $this->targetLine = $targetLine;
        // Note: $targetColumn parameter kept for backward compatibility but not used
    }

    public function enterNode(Node $node): ?int
    {
        // Track statements
        if ($node instanceof Node\Stmt) {
            $this->stmtStack[] = $node;
        }

        // Look for expressions at the target location (but not assignments or variables)
        if ($node instanceof Expr && !($node instanceof Variable) && !($node instanceof Expr\Assign)) {
            if ($node->hasAttribute('startLine')) {
                $startLine = $node->getAttribute('startLine');

                // Match expressions on the target line
                if ($startLine === $this->targetLine) {
                    // Store the first match as best match
                    if ($this->bestMatch === null) {
                        $this->bestMatch = $node;
                        $this->parentStatement = !empty($this->stmtStack) ? end($this->stmtStack) : null;
                    } elseif ($this->isLessSpecific($node, $this->bestMatch)) {
                        // Prefer larger (less specific / outermost) expressions on the same line
                        $this->bestMatch = $node;
                        $this->parentStatement = !empty($this->stmtStack) ? end($this->stmtStack) : null;
                    }
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // Pop statements
        if ($node instanceof Node\Stmt) {
            if (!empty($this->stmtStack) && end($this->stmtStack) === $node) {
                array_pop($this->stmtStack);
            }
        }

        return null;
    }

    private function isLessSpecific(Node $node1, Node $node2): bool
    {
        // A node is less specific if it contains another node
        if (!$node1->hasAttribute('startFilePos') || !$node1->hasAttribute('endFilePos')) {
            return false;
        }
        if (!$node2->hasAttribute('startFilePos') || !$node2->hasAttribute('endFilePos')) {
            return true;
        }

        $start1 = $node1->getAttribute('startFilePos');
        $end1 = $node1->getAttribute('endFilePos');
        $start2 = $node2->getAttribute('startFilePos');
        $end2 = $node2->getAttribute('endFilePos');

        // Node1 is less specific if it contains node2
        return $start1 <= $start2 && $end1 >= $end2 &&
               !($start1 === $start2 && $end1 === $end2);
    }

    public function getExpression(): ?Expr
    {
        // Return the best match found
        return $this->bestMatch;
    }

    public function getParentStatement(): ?Node
    {
        return $this->parentStatement;
    }
}
