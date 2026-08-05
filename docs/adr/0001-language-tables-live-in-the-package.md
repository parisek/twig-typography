# 1. Language tables live in the package, not in each project

- **Status:** Accepted
- **Date:** 2026-08-05
- **Deciders:** @parisek

## Context

Smart-quote and smart-dash pairs are a property of the language being
typeset, not of any one project. Before 1.3.0 there was no package-level
notion of language at all: each downstream project carried its own
typography config (or none), and every one of them had independently arrived
at, or defaulted into, the same wrong answer for Czech —
`set_smart_quotes_primary: doubleLow9`, which renders `„…”`. That pair is
correct for Polish, not Czech; Czech needs `doubleLow9Reversed`, which closes
`“`. Every project carrying that config was wrong the same way, and any
project with no typography config at all fell through to PHP-Typography's own
`Settings(true)` defaults, which don't know about Czech either.

The obvious first fix — correct the setting in each project's own config
file — was rejected before it was tried. It would have repaired one project
and left the rest of the fleet wrong, because the defect wasn't a bad value
in one file, it was the absence of a shared source of truth for what
"correct Czech" means. A per-project language file also has no mechanism to
receive a correction later: fixing Czech again in the future would mean
re-touching every project's config a second time, the same way the bug
itself required it once already.

## Decision

Language tables are bundled inside `parisek/twig-typography` itself, in the
shipped `typography.yml`'s `languages:` map, and applied on every render
regardless of what a consuming project passes. `SettingsLoader::global()`
and `SettingsLoader::language()` read this bundled file from
`SettingsLoader::packagePath()`, layered beneath a project's own `$config`
in `TypographyExtension::applyTypography()`'s merge order (package global →
package language → project global → project language → per-call
arguments).

A project cannot override a single language wholesale by omission — it gets
the package's per-language table for every locale it doesn't explicitly
override. To change a language's behavior for one project only, the project
supplies its own `languages:` entry using the same shape (see ADR 0002),
which is merged on top rather than replacing the package's answer.

## Consequences

- Correcting a language — fixing Czech's quote pair, adding a new language
  table — is now a package release (a `composer update`), not a
  find-and-replace across every downstream repo. The fleet converges on the
  fix by updating a dependency, the same mechanism that would have re-spread
  the original bug if it had been introduced here instead of duplicated.
- A project cannot fix its own language's typesetting by editing its own
  config until the package ships the fix — it has to wait for a release, or
  reach for the `languages:` override in the meantime (its escape hatch, not
  its primary path). This is a deliberate loss of per-project autonomy over
  language rules, traded for the fleet no longer being able to drift into
  thirteen different (wrong) answers for the same language.
- A project with a typo'd or nonexistent settings path is no longer
  "unconfigured" in the old sense — it still gets the package's house
  policy and per-language tables, just no project-specific overrides. See
  the 1.3.0 CHANGELOG entry: this is a rendered-output-changing release for
  any consumer that was relying on `Settings(true)`'s raw defaults.
- Guard: `tests/LanguageTableTest.php` exercises the bundled per-language
  tables directly, including the Czech quote pair regression.
