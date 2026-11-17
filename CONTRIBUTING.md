# Contributing to particle-academy/fancy-seo

Notes for anyone, human or agent, working ON this package's code. How to USE the
package is in [AGENTS.md](./AGENTS.md); what it is, is in [README.md](./README.md).
`CLAUDE.md` is a symlink to this file, so Claude Code reads these notes when it
works in this repo.

## Commands

```bash
composer install
composer test          # pest
vendor/bin/pint        # format
```

## Tests

Pest, under `tests/` (`tests/Feature/`, with `tests/Pest.php` and
`tests/TestCase.php`).

## Layout

- `src/` — the `FancySeo\` namespace (service provider, facade, `JsonLd`).
- `config/fancy-seo.php` — published with `--tag=fancy-seo-config`.
- `resources/` — the `<x-fancy-seo::head>` view.

## Changelog

Update `CHANGELOG.md` in the same commit as the change.
