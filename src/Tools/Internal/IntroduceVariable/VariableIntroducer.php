<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\IntroduceVariable;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to introduce a variable from an expression.
 */
class VariableIntroducer extends NodeVisitorAbstract
{
    private Expr $targetExpr;
    private Node $parentStmt;
    private string $variableName;
    private bool $introduced = false;

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
        if ($node === $this->targetExpr && !$this->introduced) {
            return new Variable($this->variableName);
        }

        // Insert the variable declaration before the parent statement
        if ($node === $this->parentStmt && !$this->introduced) {
            $this->introduced = true;

            // Create the variable declaration statement
            $declaration = new Expression(
                new Expr\Assign(
                    new Variable($this->variableName),
                    clone $this->targetExpr
                )
            );

            // Return an array to insert the declaration before the current statement
            return [
                $declaration,
                $node,
            ];
        }

        return null;
    }
}
