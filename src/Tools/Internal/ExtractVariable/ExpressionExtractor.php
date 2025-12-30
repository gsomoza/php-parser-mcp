<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\ExtractVariable;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to extract an expression into a variable.
 */
class ExpressionExtractor extends NodeVisitorAbstract
{
    private Expr $targetExpr;
    private Node $parentStmt;
    private string $variableName;
    private bool $extracted = false;

    public function __construct(Expr $targetExpr, Node $parentStmt, string $variableName)
    {
        $this->targetExpr = $targetExpr;
        $this->parentStmt = $parentStmt;
        $this->variableName = $variableName;
    }

    /**
     * @return Node|array<Node>|null
     */
    public function leaveNode(Node $node)
    {
        // Replace the target expression with a variable reference
        if ($node === $this->targetExpr && !$this->extracted) {
            return new Variable($this->variableName);
        }

        // Insert the variable assignment before the parent statement
        if ($node === $this->parentStmt && !$this->extracted) {
            $this->extracted = true;

            // Create the assignment statement
            $assignment = new Expression(
                new Expr\Assign(
                    new Variable($this->variableName),
                    clone $this->targetExpr
                )
            );

            // Return an array to insert the assignment before the current statement
            return [
                $assignment,
                $node,
            ];
        }

        return null;
    }
}
