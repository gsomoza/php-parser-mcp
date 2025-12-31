# Test Fixtures

This directory contains fixtures for testing refactoring tools. Each subdirectory corresponds to a specific tool.

## Structure

```
tests/Fixtures/
├── RenameVariableTool/
│   ├── rename_in_function.php
│   ├── rename_in_method.php
│   └── ...
├── ExtractMethodTool/
│   ├── extract_simple_method.php
│   └── ...
├── IntroduceVariableTool/
│   └── ...
└── ExtractVariableTool/
    └── ...
```

## Fixture Format

Each fixture is a PHP file with:
1. Test parameters in comment headers (starting with `//`)
2. The PHP code to be tested

### Example

```php
<?php
// line: 6
// oldName: $oldVar
// newName: $newVar

function test() {
    $oldVar = 1;
    $result = $oldVar + 2;
    return $result;
}
```

## Parameter Formats

### RenameVariableTool
```php
// line: {line_number}
// oldName: {variable_name}
// newName: {new_variable_name}
```

### ExtractMethodTool
```php
// range: {start_line}-{end_line}
// methodName: {method_name}
```

### IntroduceVariableTool / ExtractVariableTool
```php
// position: {line}:{column}
// variableName: {variable_name}
```

## Adding a New Fixture

1. Create a new `.php` file in the appropriate tool directory
2. Add parameter comments at the top
3. Add the PHP code to test
4. Run tests with `UPDATE_SNAPSHOTS=true composer test` to generate snapshots

**Important:** Line/position numbers must account for the comment header lines (typically add 3 to your desired line number).

## How Tests Work

1. **Discovery**: Test framework automatically finds all `.php` files in fixture directories
2. **Parsing**: Extracts parameters from comment headers
3. **Execution**: Runs the tool with the fixture code and parameters
4. **Validation**: Compares output against saved snapshots

## Snapshots

Snapshots are stored in `tests/Tools/__snapshots__/` and are automatically created/updated when tests run with `UPDATE_SNAPSHOTS=true`.

Each fixture generates a corresponding snapshot file containing the expected refactored output.
