<?php

declare(strict_types=1);

namespace Somoza\PhpRefactorMcp\Tools;

use League\Flysystem\FilesystemOperator;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpParser\NodeTraverser;
use Somoza\PhpRefactorMcp\Helpers\FilesystemFactory;
use Somoza\PhpRefactorMcp\Helpers\RefactoringHelpers;
use Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod\MethodExtractor;
use Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod\StatementRangeFinder;
use Somoza\PhpRefactorMcp\Tools\Internal\ExtractMethod\VariableAnalyzer;
use Somoza\PhpRefactorMcp\ValueObjects\SelectionRange;

class ExtractMethodTool
{
    private FilesystemOperator $filesystem;

    public function __construct(?FilesystemOperator $filesystem = null)
    {
        $this->filesystem = $filesystem ?? FilesystemFactory::createLocalFilesystem();
    }
    /**
     * Extract a block of code into a separate method.
     *
     * @param string $file Path to the PHP file
     * @param string $selectionRange Range in format 'startLine:startColumn-endLine:endColumn' or 'startLine-endLine'
     * @param string $methodName Name for the new method
     *
     * @return array{success: bool, code?: string, file?: string, message?: string, error?: string}
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
            type: 'string',
            description: "Range in format 'startLine:startColumn-endLine:endColumn' or 'startLine-endLine'"
        )]
        string $selectionRange,
        #[Schema(
            type: 'string',
            description: 'Name for the new method'
        )]
        string $methodName
    ): array {
        // Parse the selection range
        $range = SelectionRange::tryParse($selectionRange);
        if ($range === null) {
            return [
                'success' => false,
                'error' => 'Invalid selection range format. Use \'startLine:startColumn-endLine:endColumn\' or \'startLine-endLine\'',
            ];
        }

        // Validate method name
        if (empty($methodName)) {
            return [
                'success' => false,
                'error' => 'Method name cannot be empty',
            ];
        }

        if ($range->startLine > $range->endLine) {
            return [
                'success' => false,
                'error' => "Start line ({$range->startLine}) must be less than or equal to end line ({$range->endLine})",
            ];
        }

        return RefactoringHelpers::applyFileEdit(
            $this->filesystem,
            $file,
            fn($code) => $this->extractMethodInSource($code, $range->startLine, $range->endLine, $methodName),
            "Successfully extracted method '{$methodName}' from {$selectionRange} in {$file}"
        );
    }

    /**
     * Extract method from source code.
     *
     * @param string $code Source code
     * @param int $startLine Starting line number
     * @param int $endLine Ending line number
     * @param string $methodName Name for the new method
     *
     * @return string Refactored source code
     */
    private function extractMethodInSource(string $code, int $startLine, int $endLine, string $methodName): string
    {
        // Parse the code
        $ast = RefactoringHelpers::parseCode($code);

        // Find the statements to extract and their context
        $statementFinder = new StatementRangeFinder($startLine, $endLine);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($statementFinder);
        $traverser->traverse($ast);

        $statementsToExtract = $statementFinder->getStatements();
        $parentMethod = $statementFinder->getParentMethod();
        $parentClass = $statementFinder->getParentClass();

        if (empty($statementsToExtract)) {
            throw new \RuntimeException("Could not find statements between lines {$startLine} and {$endLine}");
        }

        if ($parentClass === null) {
            throw new \RuntimeException("Can only extract methods within a class");
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
        return RefactoringHelpers::printCode($ast);
    }
}
