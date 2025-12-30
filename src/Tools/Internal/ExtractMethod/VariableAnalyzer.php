<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Analyzes variables in extracted code to determine parameters and return values.
 */
class VariableAnalyzer
{
    /** @var array<Node\Stmt> */
    private array $statementsToExtract;
    /** @var Node\Stmt\ClassMethod|Node\Stmt\Function_|null */
    private ?Node $parentMethod;
    private int $startLine;
    private int $endLine;

    /**
     * @param array<Node\Stmt> $statementsToExtract
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_|null $parentMethod
     */
    public function __construct(array $statementsToExtract, ?Node $parentMethod, int $startLine, int $endLine)
    {
        $this->statementsToExtract = $statementsToExtract;
        $this->parentMethod = $parentMethod;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    /**
     * @return array<Param>
     */
    public function getParameters(): array
    {
        // Variables used in extracted code
        $usedVars = $this->findVariables($this->statementsToExtract);

        // Variables defined before the extracted code in the parent method
        $definedBefore = $this->findVariablesDefinedBefore();

        // Parameters are variables used but not defined in extracted code,
        // and defined before the extracted code
        $params = [];
        foreach ($usedVars as $varName) {
            if (in_array($varName, $definedBefore, true)) {
                $params[] = new Param(new Variable($varName));
            }
        }

        return $params;
    }

    /**
     * @return array<string>
     */
    public function getReturnVariables(): array
    {
        // Variables assigned in extracted code
        $assignedVars = $this->findAssignedVariables($this->statementsToExtract);

        // Variables used after the extracted code
        $usedAfter = $this->findVariablesUsedAfter();

        // Return variables are those assigned in extracted code and used after
        $returnVars = [];
        foreach ($assignedVars as $varName) {
            if (in_array($varName, $usedAfter, true)) {
                $returnVars[] = $varName;
            }
        }

        return $returnVars;
    }

    /**
     * @param array<Node\Stmt> $statements
     *
     * @return array<string>
     */
    private function findVariables(array $statements): array
    {
        $vars = [];
        $visitor = new class ($vars) extends NodeVisitorAbstract {
            /** @var array<string> */
            private array $vars;

            /**
             * @param array<string> $vars
             */
            public function __construct(array &$vars)
            {
                $this->vars = &$vars;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this') {
                    if (!in_array($node->name, $this->vars, true)) {
                        $this->vars[] = $node->name;
                    }
                }
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        foreach ($statements as $stmt) {
            $traverser->traverse([$stmt]);
        }

        return $vars;
    }

    /**
     * @param array<Node\Stmt> $statements
     *
     * @return array<string>
     */
    private function findAssignedVariables(array $statements): array
    {
        $assigned = [];
        $visitor = new class ($assigned) extends NodeVisitorAbstract {
            /** @var array<string> */
            private array $assigned;

            /**
             * @param array<string> $assigned
             */
            public function __construct(array &$assigned)
            {
                $this->assigned = &$assigned;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Expr\Assign) {
                    if ($node->var instanceof Variable && is_string($node->var->name)) {
                        if (!in_array($node->var->name, $this->assigned, true)) {
                            $this->assigned[] = $node->var->name;
                        }
                    }
                }
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        foreach ($statements as $stmt) {
            $traverser->traverse([$stmt]);
        }

        return $assigned;
    }

    /**
     * @return array<string>
     */
    private function findVariablesDefinedBefore(): array
    {
        if ($this->parentMethod === null) {
            return [];
        }

        $defined = [];

        // Get all statements in parent method
        $stmts = $this->parentMethod->stmts ?? [];

        foreach ($stmts as $stmt) {
            if (!$stmt->hasAttribute('startLine')) {
                continue;
            }

            $stmtLine = $stmt->getAttribute('startLine');

            // Only look at statements before the extracted range
            if ($stmtLine >= $this->startLine) {
                break;
            }

            // Find assignments in this statement
            $visitor = new class ($defined) extends NodeVisitorAbstract {
                /** @var array<string> */
                private array $defined;

                /**
                 * @param array<string> $defined
                 */
                public function __construct(array &$defined)
                {
                    $this->defined = &$defined;
                }

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Expr\Assign) {
                        if ($node->var instanceof Variable && is_string($node->var->name)) {
                            if (!in_array($node->var->name, $this->defined, true)) {
                                $this->defined[] = $node->var->name;
                            }
                        }
                    }
                    return null;
                }
            };

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse([$stmt]);
        }

        return $defined;
    }

    /**
     * @return array<string>
     */
    private function findVariablesUsedAfter(): array
    {
        if ($this->parentMethod === null) {
            return [];
        }

        $used = [];

        // Get all statements in parent method
        $stmts = $this->parentMethod->stmts ?? [];

        foreach ($stmts as $stmt) {
            if (!$stmt->hasAttribute('startLine')) {
                continue;
            }

            $stmtLine = $stmt->getAttribute('startLine');

            // Only look at statements after the extracted range
            if ($stmtLine <= $this->endLine) {
                continue;
            }

            // Find variable uses in this statement
            $visitor = new class ($used) extends NodeVisitorAbstract {
                /** @var array<string> */
                private array $used;

                /**
                 * @param array<string> $used
                 */
                public function __construct(array &$used)
                {
                    $this->used = &$used;
                }

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this') {
                        if (!in_array($node->name, $this->used, true)) {
                            $this->used[] = $node->name;
                        }
                    }
                    return null;
                }
            };

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse([$stmt]);
        }

        return $used;
    }
}
