# GitHub Copilot Instructions

## Project Overview
This is a Model Context Protocol (MCP) server that provides PHP refactoring tools powered by nikic/PHP-Parser.

## Coding Standards
- Follow PSR-4 autoloading standards
- Follow PSR-12 coding style guide
- Use PHP 8.1+ features where appropriate
- Maintain compatibility with PHP 7.1 code parsing

## Architecture
- MCP Tools are discovered via PHP attributes
- Use `php-mcp/server` for MCP server implementation
- Use `nikic/PHP-Parser` for PHP code parsing and manipulation
- Server uses stdio transport for communication

## Code Organization
- Place all source code in `src/` directory
- Place all tests in `tests/` directory
- Namespace: `Somoza\PhpParserMcp`
- Tools should be in `src/Tools/` directory

## Testing
- Use PHPUnit for testing
- Write unit tests for all tools
- Test both successful operations and error cases
- Mock external dependencies where appropriate

## Documentation
- Document all public methods and classes
- Include usage examples in README
- Keep PHPDoc blocks up to date
- Document tool parameters and return values

## Dependencies
- Minimize external dependencies
- Prefer stable, well-maintained packages
- Keep dependencies up to date

## Error Handling
- Use exceptions for error conditions
- Provide clear, actionable error messages
- Validate all input parameters
- Handle edge cases gracefully
