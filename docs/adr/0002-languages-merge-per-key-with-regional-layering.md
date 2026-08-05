# 2. `languages:` merges per key, and a regional entry layers on its base language

- **Status:** Accepted
- **Date:** 2026-08-05
- **Deciders:** @parisek

## Context

A language entry in `typography.yml`'s `languages:` map (package-bundled or
project-supplied, same shape) exists to state only what genuinely varies for
that language — the Czech entry sets `set_diacritic_language`,
`set_smart_quotes_primary`/`_secondary`, `set_single_character_word_spacing`,
and nothing else; every key it doesn't mention falls through to the global
section above it. A block-replacement scheme, where a language entry fully
redeclares its own settings, was considered and rejected: it would force
every one of the thirteen bundled tables to repeat the full set of global
defaults, and it would make adding a fourteenth language require copying and
re-verifying that whole list rather than stating the two or three keys that
differ from the house policy.

Regional tags (`de-CH`, `en-GB`, `<language>-<REGION>`) sit inside the same
`languages:` map and raise the same question one level down: should
`en-GB`'s entry restate everything `en` already says, or only its own
delta? The layering answer was implemented, then a real defect showed the
first version of the resolution logic didn't actually deliver it.
`TypographyExtension::resolveLanguageSettings()` walks the locale's
candidate tags (most-specific first, e.g. `['en-GB', 'en']`) and originally
took the *first* matching entry and stopped — the classic "first match
wins" fallback-chain shape. Under that logic, as soon as `en-GB` existed at
all, `en`'s own settings were never consulted for an `en_GB` render: `en-GB`
silently lost `en`'s `set_smart_ordinal_suffix`, a difference nobody chose
and nothing signalled. The regional entry wasn't additive in practice even
though the documentation and intent said it was.

## Decision

`languages:` merges per key at both levels. Global section, then each
present language tag in the candidate list, are folded together with
`array_merge()`, walking the candidates **least-specific to most-specific**
(`array_reverse($candidates)` — see `resolveLanguageSettings()` and its
project-config counterpart `resolveProjectLanguageSettings()`) so a later,
more specific layer's keys overwrite an earlier, less specific layer's keys,
while every key the more specific layer doesn't mention survives from
underneath. For `de_CH`: `de-CH` is merged over `de`, which is merged over
the global section. A regional entry only needs to state the keys that
genuinely differ from its base language.

## Consequences

- Adding a language or a regional variant costs a handful of lines, not a
  full restated block — the thirteen bundled tables in `typography.yml`
  stay short and reviewable.
- An entry is cheap to write but hard to read in isolation: its full
  effective settings for a render are the fold of global + base language +
  region, not what's visible in its own five or six lines. Understanding
  what `de-CH` actually does requires reading `de` and the global section
  alongside it — there is no single place that shows the resolved value.
- The "first match wins" version was live logic, not a discarded draft — it
  shipped the exact `en-GB`-loses-`en`'s-ordinal-suffix bug described above
  before being replaced by the fold. Anyone re-deriving this resolution
  order from scratch should expect to re-discover the same failure mode; it
  is not hypothetical.
- Guard: `tests/SettingsLoaderTest.php` and `tests/LanguageTableTest.php`
  cover the merge order, including a regional-over-base case exercising
  exactly the scenario the first implementation got wrong.
