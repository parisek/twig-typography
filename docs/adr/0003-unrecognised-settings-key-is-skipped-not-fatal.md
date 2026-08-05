# 0003. An unrecognised settings key is skipped, not fatal

## Context

`TypographyExtension::applyTypography()` applies the merged settings array
by calling `$settings->{$key}($value)` for each key against
PHP-Typography's `Settings` object — every real option is exposed as a
`set_*` mutator. Before this guard existed, a key that didn't correspond to
a real, callable `Settings` method — a typo'd option name in a project's own
YAML, most plausibly, but also any key meant for a future PHP-Typography
version this package hasn't caught up to yet — raised a fatal
`Error: Call to undefined method Settings::...()` straight out of the
render path. That took the whole page down over a single misspelled
setting, and it was also the reason the `languages:` map itself couldn't be
introduced safely: passed through to `Settings` unfiltered, the `languages`
document-structure key would hit the identical fatal.

The first guard considered was `method_exists($settings, $key)`. It was
rejected because it doesn't check visibility: `Settings` has real, protected
methods — `get_style` among them — that `method_exists()` reports as
existing just as readily as a public `set_*` mutator. A key that happened to
collide with a protected or private member name would pass the
`method_exists()` check and then fatal anyway, with a different message
(`Call to protected method`) but the identical page-down outcome the guard
was meant to prevent.

## Decision

Guard with `str_starts_with($key, 'set_')` combined with
`is_callable([$settings, $key])`, and skip the key silently — no logging,
no exception — when either check fails, leaving every other key in the same
call to still apply. `is_callable()` with the two-element-array syntax
resolves visibility from the *calling scope* (this file, not from inside
the `Settings` class), so it only returns true for methods actually
invocable from here — i.e. public ones — which is exactly what
`method_exists()` was missing. The `set_` prefix requirement is an
additional belt-and-suspenders check: every meaningful `Settings` option is
exposed as a `set_*` mutator, so requiring the prefix also rules out
accidentally calling an unrelated public method that happens to be
callable but isn't a settings mutator at all.

## Consequences

- A misspelled or unsupported setting no longer takes the page down. It is
  silently ignored while the rest of the call's settings still apply.
- The trade-off is genuine, not incidental: a project can now believe a
  setting is active — because its YAML says so and nothing complains — when
  it is in fact doing nothing, with no signal anywhere that it was dropped.
  This was accepted deliberately, on the grounds that broken configuration
  should cost typography (a quote pair or a spacing rule silently not
  applying), not the whole page.
- This guard is also what made `languages:` safe to introduce as a document
  key at all (see ADR 0001, ADR 0002): without it, forwarding `languages`
  itself to `Settings` would hit the identical undefined-method fatal this
  guard exists to prevent.
- Guard: `tests/TypographyExtensionTest.php` exercises an unrecognised key
  alongside valid ones in the same call and asserts the valid ones still
  take effect and no exception is thrown.
