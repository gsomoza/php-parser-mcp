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
    private int $startColumn;
    private int $endLine;
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

        // Check if node overlaps with selection range (line-based)
        if ($nodeStartLine > $this->endLine || $nodeEndLine < $this->startLine) {
            return false; // No overlap
        }

        // If we have column information and the selection is specific, use it for more precision
        if ($this->startColumn > 0 && $this->endColumn > 0 &&
            $node->hasAttribute('startFilePos') && $node->hasAttribute('endFilePos')) {
            // For single-line selections with column info, check column overlap
            if ($this->startLine === $this->endLine && $nodeStartLine === $nodeEndLine) {
                // Get approximate column positions (this is a simplified approach)
                $nodeStartCol = $node->getAttribute('startFilePos');
                $nodeEndCol = $node->getAttribute('endFilePos');
                // Note: These are file positions, not columns, but can be used for comparison
                // A more precise implementation would need to track actual columns
            }
        }

        return true; // Node overlaps with selection range
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
