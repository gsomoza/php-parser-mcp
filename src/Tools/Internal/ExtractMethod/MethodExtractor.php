<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to extract statements into a new method.
 */
class MethodExtractor extends NodeVisitorAbstract
{
    /** @var array<Node\Stmt> */
    private array $statementsToExtract;
    /** @var Node\Stmt\ClassMethod|Node\Stmt\Function_|null */
    private ?Node $parentMethod;
    private Node\Stmt\Class_ $parentClass;
    private string $methodName;
    /** @var array<Param> */
    private array $params;
    /** @var array<string> */
    private array $returnVars;
    private int $startLine;
    private int $endLine;
    private bool $extracted = false;
    private bool $methodAdded = false;

    /**
     * @param array<Node\Stmt> $statementsToExtract
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_|null $parentMethod
     * @param array<Param> $params
     * @param array<string> $returnVars
     */
    public function __construct(
        array $statementsToExtract,
        ?Node $parentMethod,
        Node\Stmt\Class_ $parentClass,
        string $methodName,
        array $params,
        array $returnVars,
        int $startLine,
        int $endLine
    ) {
        $this->statementsToExtract = $statementsToExtract;
        $this->parentMethod = $parentMethod;
        $this->parentClass = $parentClass;
        $this->methodName = $methodName;
        $this->params = $params;
        $this->returnVars = $returnVars;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    public function leaveNode(Node $node)
    {
        // Add the new method to the class
        if ($node === $this->parentClass && !$this->methodAdded) {
            $this->methodAdded = true;

            // Create the new method body
            $methodStmts = [];
            foreach ($this->statementsToExtract as $stmt) {
                $methodStmts[] = clone $stmt;
            }

            // Add return statement if needed
            if (!empty($this->returnVars)) {
                if (count($this->returnVars) === 1) {
                    $methodStmts[] = new Return_(new Variable($this->returnVars[0]));
                } else {
                    // Return array of variables
                    $arrayItems = [];
                    foreach ($this->returnVars as $varName) {
                        $arrayItems[] = new Node\Expr\ArrayItem(new Variable($varName));
                    }
                    $methodStmts[] = new Return_(new Expr\Array_($arrayItems));
                }
            }

            // Create the new private method
            $newMethod = new ClassMethod($this->methodName, [
                'flags' => Node\Stmt\Class_::MODIFIER_PRIVATE,
                'params' => $this->params,
                'stmts' => $methodStmts,
            ]);

            // Add method to class
            $node->stmts[] = $newMethod;
        }

        // Replace extracted statements with method call in parent method
        if ($node === $this->parentMethod && !$this->extracted) {
            $this->extracted = true;

            $newStmts = [];
            $replacementAdded = false;

            // We know $node is the parent method (ClassMethod or Function_), which has stmts property
            /** @var ClassMethod|Stmt\Function_ $node */
            $stmts = $node->stmts ?? [];

            foreach ($stmts as $stmt) {
                // Skip statements without line information - they should not be in our range
                if (!$stmt->hasAttribute('startLine')) {
                    $newStmts[] = $stmt;
                    continue;
                }

                $stmtLine = $stmt->getAttribute('startLine');

                // Before the range: keep statement
                if ($stmtLine < $this->startLine) {
                    $newStmts[] = $stmt;
                } elseif ($stmtLine >= $this->startLine && $stmtLine <= $this->endLine && !$replacementAdded) {
                    // At start of range: add method call
                    $replacementAdded = true;

                    // Create method call arguments
                    $args = [];
                    foreach ($this->params as $param) {
                        if ($param->var instanceof Variable) {
                            $args[] = new Node\Arg(new Variable($param->var->name));
                        }
                    }

                    // Create method call
                    $methodCall = new Expr\MethodCall(
                        new Variable('this'),
                        $this->methodName,
                        $args
                    );

                    // If there are return variables, assign them
                    if (!empty($this->returnVars)) {
                        if (count($this->returnVars) === 1) {
                            $newStmts[] = new Expression(
                                new Expr\Assign(
                                    new Variable($this->returnVars[0]),
                                    $methodCall
                                )
                            );
                        } else {
                            // List destructuring for multiple return values
                            $listItems = [];
                            foreach ($this->returnVars as $varName) {
                                $listItems[] = new Expr\ArrayItem(new Variable($varName));
                            }
                            $newStmts[] = new Expression(
                                new Expr\Assign(
                                    new Expr\List_($listItems),
                                    $methodCall
                                )
                            );
                        }
                    } else {
                        $newStmts[] = new Expression($methodCall);
                    }
                } elseif ($stmtLine >= $this->startLine && $stmtLine <= $this->endLine) {
                    // In range: skip
                } elseif ($stmtLine > $this->endLine) {
                    // After range: keep statement
                    $newStmts[] = $stmt;
                }
            }

            $node->stmts = $newStmts;
        }

        return null;
    }
}
