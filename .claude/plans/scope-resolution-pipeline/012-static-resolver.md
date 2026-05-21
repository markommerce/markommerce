# Task 012: Build StaticResolver

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that always returns a constant configured value, regardless of request or channel. Useful as a final fallback in a chain (especially for CLI/queue contexts where HTTP resolvers all return null), and as a testing aid.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/StaticResolver.php`
- Constructor: `public function __construct(private string $value)`
- Always returns `$this->value` — does NOT check channel (this is the only built-in that works on all channels)

## Requirements (Test Descriptions)

- [ ] `it returns the configured value when channel is http`
- [ ] `it returns the configured value when channel is cli`
- [ ] `it returns the configured value when channel is queue`
- [ ] `it returns the same value for repeated calls`
- [ ] `it ignores the request entirely`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
