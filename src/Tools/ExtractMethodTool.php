<?php

declare(strict_types=1);

namespace Somoza\PhpParserMcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class ExtractMethodTool
{
    private ParserFactory $parserFactory;
    private Standard $printer;

    public function __construct()
    {
        $this->parserFactory = new ParserFactory();
        $this->printer = new Standard();
    }

    /**
     * Extract a block of code into a separate method
     *
     * @param string $file Path to the PHP file
     * @param int $startLine Starting line number
     * @param int $endLine Ending line number
     * @param string $methodName Name for the new method
     * @return array{success: bool, code?: string, file?: string, error?: string}
     */
    #[McpTool(
        name: 'extract_method',
        description: 'Extract a block of code into a separate method'
    )]
    public function extract(
        #[Schema(
            type: 'string',
            description: 'Path to the PHP file'
        )]
        string $file,
        #[Schema(
            type: 'integer',
            description: 'Starting line number'
        )]
        int $startLine,
        #[Schema(
            type: 'integer',
            description: 'Ending line number'
        )]
        int $endLine,
        #[Schema(
            type: 'string',
            description: 'Name for the new method'
        )]
        string $methodName
    ): array {
        try {
            // Check if file exists
            if (!file_exists($file)) {
                return [
                    'success' => false,
                    'error' => "File not found: {$file}"
                ];
            }

            // Check if file is readable
            if (!is_readable($file)) {
                return [
                    'success' => false,
                    'error' => "File is not readable: {$file}"
                ];
            }

            // Read file contents
            $code = file_get_contents($file);
            if ($code === false) {
                return [
                    'success' => false,
                    'error' => "Failed to read file: {$file}"
                ];
            }

            // Validate inputs
            if (empty($methodName)) {
                return [
                    'success' => false,
                    'error' => 'Method name cannot be empty'
                ];
            }

            if ($startLine > $endLine) {
                return [
                    'success' => false,
                    'error' => "Start line ({$startLine}) must be less than or equal to end line ({$endLine})"
                ];
            }

            // Parse the code
            $parser = $this->parserFactory->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse code: parser returned null'
                ];
            }

            // Find the statements to extract and their context
            $statementFinder = new StatementRangeFinder($startLine, $endLine);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($statementFinder);
            $traverser->traverse($ast);

            $statementsToExtract = $statementFinder->getStatements();
            $parentMethod = $statementFinder->getParentMethod();
            $parentClass = $statementFinder->getParentClass();

            if (empty($statementsToExtract)) {
                return [
                    'success' => false,
                    'error' => "Could not find statements between lines {$startLine} and {$endLine}"
                ];
            }

            if ($parentClass === null) {
                return [
                    'success' => false,
                    'error' => "Can only extract methods within a class"
                ];
            }

            // Analyze variables
            $analyzer = new VariableAnalyzer($statementsToExtract, $parentMethod, $startLine, $endLine);
            $params = $analyzer->getParameters();
            $returnVars = $analyzer->getReturnVariables();

            // Create the new method
            $extractor = new MethodExtractor(
                $statementsToExtract,
                $parentMethod,
                $parentClass,
                $methodName,
                $params,
                $returnVars,
                $startLine,
                $endLine
            );
            $traverser = new NodeTraverser();
            $traverser->addVisitor($extractor);
            $ast = $traverser->traverse($ast);

            // Generate the modified code
            $modifiedCode = $this->printer->prettyPrintFile($ast);

            return [
                'success' => true,
                'code' => $modifiedCode,
                'file' => $file
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'error' => 'Parse error: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * NodeVisitor to find statements in a line range
 */
class StatementRangeFinder extends NodeVisitorAbstract
{
    private int $startLine;
    private int $endLine;
    private array $statements = [];
    private ?Node $parentMethod = null;
    private ?Node\Stmt\Class_ $parentClass = null;
    private array $methodStack = [];
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

    public function getStatements(): array
    {
        return $this->statements;
    }

    public function getParentMethod(): ?Node
    {
        return $this->parentMethod;
    }

    public function getParentClass(): ?Node\Stmt\Class_
    {
        return $this->parentClass;
    }
}

/**
 * Analyzes variables in extracted code to determine parameters and return values
 */
class VariableAnalyzer
{
    private array $statementsToExtract;
    private ?Node $parentMethod;
    private int $startLine;
    private int $endLine;

    public function __construct(array $statementsToExtract, ?Node $parentMethod, int $startLine, int $endLine)
    {
        $this->statementsToExtract = $statementsToExtract;
        $this->parentMethod = $parentMethod;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

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
            if (in_array($varName, $definedBefore)) {
                $params[] = new Param(new Variable($varName));
            }
        }

        return $params;
    }

    public function getReturnVariables(): array
    {
        // Variables assigned in extracted code
        $assignedVars = $this->findAssignedVariables($this->statementsToExtract);
        
        // Variables used after the extracted code
        $usedAfter = $this->findVariablesUsedAfter();

        // Return variables are those assigned in extracted code and used after
        $returnVars = [];
        foreach ($assignedVars as $varName) {
            if (in_array($varName, $usedAfter)) {
                $returnVars[] = $varName;
            }
        }

        return $returnVars;
    }

    private function findVariables(array $statements): array
    {
        $vars = [];
        $visitor = new class($vars) extends NodeVisitorAbstract {
            private array $vars;

            public function __construct(array &$vars)
            {
                $this->vars = &$vars;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this') {
                    if (!in_array($node->name, $this->vars)) {
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

    private function findAssignedVariables(array $statements): array
    {
        $assigned = [];
        $visitor = new class($assigned) extends NodeVisitorAbstract {
            private array $assigned;

            public function __construct(array &$assigned)
            {
                $this->assigned = &$assigned;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Expr\Assign) {
                    if ($node->var instanceof Variable && is_string($node->var->name)) {
                        if (!in_array($node->var->name, $this->assigned)) {
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
            $visitor = new class($defined) extends NodeVisitorAbstract {
                private array $defined;

                public function __construct(array &$defined)
                {
                    $this->defined = &$defined;
                }

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Expr\Assign) {
                        if ($node->var instanceof Variable && is_string($node->var->name)) {
                            if (!in_array($node->var->name, $this->defined)) {
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
            $visitor = new class($used) extends NodeVisitorAbstract {
                private array $used;

                public function __construct(array &$used)
                {
                    $this->used = &$used;
                }

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this') {
                        if (!in_array($node->name, $this->used)) {
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

/**
 * NodeVisitor to extract statements into a new method
 */
class MethodExtractor extends NodeVisitorAbstract
{
    private array $statementsToExtract;
    private ?Node $parentMethod;
    private Node\Stmt\Class_ $parentClass;
    private string $methodName;
    private array $params;
    private array $returnVars;
    private int $startLine;
    private int $endLine;
    private bool $extracted = false;
    private bool $methodAdded = false;

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
                'stmts' => $methodStmts
            ]);

            // Add method to class
            $node->stmts[] = $newMethod;
        }

        // Replace extracted statements with method call in parent method
        if ($node === $this->parentMethod && !$this->extracted) {
            $this->extracted = true;

            $newStmts = [];
            $inRange = false;
            $replacementAdded = false;

            foreach ($node->stmts as $stmt) {
                // Skip statements without line information - they should not be in our range
                if (!$stmt->hasAttribute('startLine')) {
                    $newStmts[] = $stmt;
                    continue;
                }

                $stmtLine = $stmt->getAttribute('startLine');

                // Before the range: keep statement
                if ($stmtLine < $this->startLine) {
                    $newStmts[] = $stmt;
                }
                // At start of range: add method call
                elseif ($stmtLine >= $this->startLine && $stmtLine <= $this->endLine && !$replacementAdded) {
                    $replacementAdded = true;
                    $inRange = true;

                    // Create method call arguments
                    $args = [];
                    foreach ($this->params as $param) {
                        $args[] = new Node\Arg(new Variable($param->var->name));
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
                }
                // In range: skip
                elseif ($stmtLine >= $this->startLine && $stmtLine <= $this->endLine) {
                    // Skip
                }
                // After range: keep statement
                elseif ($stmtLine > $this->endLine) {
                    $inRange = false;
                    $newStmts[] = $stmt;
                }
            }

            $node->stmts = $newStmts;
        }

        return null;
    }
}
