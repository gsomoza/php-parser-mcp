<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor to find statements in a line range.
 */
class StatementRangeFinder extends NodeVisitorAbstract
{
    private int $startLine;
    private int $endLine;
    /** @var array<Node\Stmt> */
    private array $statements = [];
    /** @var Node\Stmt\ClassMethod|Node\Stmt\Function_|null */
    private ?Node $parentMethod = null;
    private ?Node\Stmt\Class_ $parentClass = null;
    /** @var array<Node\Stmt\ClassMethod|Node\Stmt\Function_> */
    private array $methodStack = [];
    /** @var array<Node\Stmt\Class_> */
    private array $classStack = [];

    public function __construct(int $startLine, int $endLine)
    {
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    public function enterNode(Node $node): ?int
    {
        // Track classes
        if ($node instanceof Node\Stmt\Class_) {
            $this->classStack[] = $node;
        }

        // Track methods/functions
        if ($node instanceof ClassMethod || $node instanceof Stmt\Function_) {
            $this->methodStack[] = $node;
        }

        // Find statements in range
        if ($node instanceof Stmt && !($node instanceof ClassMethod) && !($node instanceof Stmt\Class_)) {
            if ($node->hasAttribute('startLine') && $node->hasAttribute('endLine')) {
                $nodeStart = $node->getAttribute('startLine');
                $nodeEnd = $node->getAttribute('endLine');

                // Statement is completely within the range
                if ($nodeStart >= $this->startLine && $nodeEnd <= $this->endLine) {
                    $this->statements[] = $node;

                    // Set parent method and class if not set
                    if ($this->parentMethod === null && !empty($this->methodStack)) {
                        $this->parentMethod = end($this->methodStack);
                    }
                    if ($this->parentClass === null && !empty($this->classStack)) {
                        $this->parentClass = end($this->classStack);
                    }
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // Pop method/function stack
        if ($node instanceof ClassMethod || $node instanceof Stmt\Function_) {
            if (!empty($this->methodStack) && end($this->methodStack) === $node) {
                array_pop($this->methodStack);
            }
        }

        // Pop class stack
        if ($node instanceof Node\Stmt\Class_) {
            if (!empty($this->classStack) && end($this->classStack) === $node) {
                array_pop($this->classStack);
            }
        }

        return null;
    }

    /**
     * @return array<Node\Stmt>
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    /**
     * @return Node\Stmt\ClassMethod|Node\Stmt\Function_|null
     */
    public function getParentMethod(): ?Node
    {
        return $this->parentMethod;
    }

    public function getParentClass(): ?Node\Stmt\Class_
    {
        return $this->parentClass;
    }
}
