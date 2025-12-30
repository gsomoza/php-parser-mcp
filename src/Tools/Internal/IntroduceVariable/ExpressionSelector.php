<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\IntroduceVariable;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to find an expression within a specific range.
 */
class ExpressionSelector extends NodeVisitorAbstract
{
    private int $startLine;
    /** @phpstan-ignore-next-line property.unusedWritten */
    private int $startColumn;
    private int $endLine;
    /** @phpstan-ignore-next-line property.unusedWritten */
    private int $endColumn;
    private ?Node $parentStatement = null;
    /** @var array<Node\Stmt> */
    private array $stmtStack = [];
    private ?Expr $bestMatch = null;

    public function __construct(int $startLine, int $startColumn, int $endLine, int $endColumn)
    {
        $this->startLine = $startLine;
        $this->startColumn = $startColumn;
        $this->endLine = $endLine;
        $this->endColumn = $endColumn;
    }

    public function enterNode(Node $node): ?int
    {
        // Track statements
        if ($node instanceof Node\Stmt) {
            $this->stmtStack[] = $node;
        }

        // Look for expressions within the target range (but not assignments or variables)
        if ($node instanceof Expr && !($node instanceof Variable) && !($node instanceof Expr\Assign)) {
            if ($this->isInRange($node)) {
                // Store or update the best match
                if ($this->bestMatch === null) {
                    $this->bestMatch = $node;
                    $this->parentStatement = !empty($this->stmtStack) ? end($this->stmtStack) : null;
                } elseif ($this->isBetterMatch($node, $this->bestMatch)) {
                    // Prefer expressions that better match the selection range
                    $this->bestMatch = $node;
                    $this->parentStatement = !empty($this->stmtStack) ? end($this->stmtStack) : null;
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

    /**
     * Check if a node is within or overlaps the selection range.
     */
    private function isInRange(Node $node): bool
    {
        if (!$node->hasAttribute('startLine') || !$node->hasAttribute('endLine')) {
            return false;
        }

        $nodeStartLine = $node->getAttribute('startLine');
        $nodeEndLine = $node->getAttribute('endLine');

        // Simple case: selection is on a single line
        if ($this->startLine === $this->endLine) {
            return $nodeStartLine === $this->startLine;
        }

        // Check if node overlaps with selection range
        return ($nodeStartLine <= $this->endLine) && ($nodeEndLine >= $this->startLine);
    }

    /**
     * Determine if node1 is a better match than node2 for the selection.
     * Prefer the largest expression that is still within the selection.
     */
    private function isBetterMatch(Node $node1, Node $node2): bool
    {
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

        $length1 = $end1 - $start1;
        $length2 = $end2 - $start2;

        // Prefer larger expressions (less specific, more encompassing)
        return $length1 > $length2;
    }

    public function getExpression(): ?Expr
    {
        return $this->bestMatch;
    }

    public function getParentStatement(): ?Node
    {
        return $this->parentStatement;
    }
}
