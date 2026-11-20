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
`tests/TestCase.php`). `tests/Host/` runs on `tests/HostTestCase.php`, a host
whose routes load after the package boots, as `routes/web.php` does; use it for
anything about route precedence.

## The `{path}.md` catch-all

It is registered in `boot()`, so it is in the route list BEFORE the host's
routes, and Laravel takes the first match. A `booted()` callback narrows its
`path` requirement to skip every host GET route ending in `.md`. Keep that a
`where` on the route: moving the catch-all after the host's routes (or making
it a fallback route) would hand `blog/hello.md` to a host's `blog/{slug}`,
whose parameter matches `hello.md`. The `where` is compiled into the route
cache. `tests/Host/MarkdownRoutePrecedenceTest.php` covers both, compiled and
not.

## Layout

- `src/` — the `FancySeo\` namespace (service provider, facade, `JsonLd`).
- `config/fancy-seo.php` — published with `--tag=fancy-seo-config`.
- `resources/` — the `<x-fancy-seo::head>` view.

## Changelog

Update `CHANGELOG.md` in the same commit as the change.
