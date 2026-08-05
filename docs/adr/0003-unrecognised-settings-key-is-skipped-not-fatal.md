# 3. An unrecognised settings key is skipped, not fatal

- **Status:** Accepted
- **Date:** 2026-08-05
- **Deciders:** @parisek

## Context

Before this guard, an unrecognised settings key — most plausibly a typo'd
option name in a project's own YAML — raised a fatal
`Error: Call to undefined method Settings::...()` straight out of the
render path, taking the whole page down over one misspelled setting.
`method_exists($settings, $key)` was tried first and rejected: it doesn't
check visibility, so a key colliding with a real but protected `Settings`
method (`get_style`, for one) passes the check and then fatals anyway with
a different message.

## Decision

Guard with `str_starts_with($key, 'set_')` combined with
`is_callable([$settings, $key])`, skipping the key silently when either
check fails. `is_callable()` resolves visibility from the calling scope, so
unlike `method_exists()` it only returns true for methods actually
invocable from here.

## Consequences

Broken configuration now costs typography, not the page — a misspelled or
unsupported setting is silently dropped, and nothing signals that it
happened. A project can believe a setting is active, because its YAML says
so and nothing complains, when it is in fact doing nothing. That trade was
made deliberately. Guard: `tests/TypographyExtensionTest.php`.
