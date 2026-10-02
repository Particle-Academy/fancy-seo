# Changelog

Notable changes to `particle-academy/fancy-seo`.

**BREAKING** marks anything that can stop working on upgrade. This package is
pre-1.0, so breaking changes land in MINOR releases — read those entries before
upgrading.

> Entries below **1.0** were reconstructed from git history when this file was
> introduced, so they summarise commit subjects rather than consumer impact.
> Everything from the next release onward is written by hand, in the same commit
> as the change.

---

## [0.6.0] - 2026-10-02

### Fixed

- **BREAKING (loudly, on purpose): a sitemap provider that RETURNS its URLs
  instead of adding them now throws instead of silently producing an empty
  sitemap.**

  `sitemap()` hands your closure a `SitemapBuilder` and **discards the return
  value**. So this:

  ```php
  FancySeo::sitemap(fn () => ['/', '/about']);          // WRONG
  ```

  contributed nothing, and `/sitemap.xml` answered **200** with a valid, empty
  `<urlset>`. There was no exception, no log line and no signal at any layer — a
  silently empty sitemap looks exactly like a site with nothing to list, which is
  why it survives review and deploys. Reported by the GuardCard team after it
  cost them real time.

  **What you must do:** nothing, unless you have this bug — in which case your
  sitemap has been empty all along and you now get told. The fix is to add rather
  than return:

  ```php
  FancySeo::sitemap(fn ($map) => $map->addMany(['/', '/about']));
  ```

  **Why this is BREAKING even though it only fires on broken code:** a route that
  answered 200 can now answer 500. That is the correct trade — an empty sitemap is
  a silent SEO outage, and a loud failure is strictly better than a quiet one —
  but it is a behaviour change and belongs under this heading rather than buried.

  **It fires only on the case that cannot mean anything else:** the provider
  returned a list AND added nothing. Deliberately *not* errors: returning the
  builder (`fn ($map) => $map->add('/')` — `add()` returns `$this`, so the
  idiomatic chain returns a builder); adding URLs *and* returning a list (sloppy,
  not broken — throwing would break live sitemaps to punish a style); and adding
  nothing while returning nothing (legitimate — a feature flag off, a query with
  no rows). The message names the provider's position, how many URLs were dropped,
  and what to call instead, because "invalid provider" would just send you back
  here.


## [Unreleased]

## 0.5.0 — 2026-08-07

### Changed

- **BREAKING — PHP 8.2 is no longer supported.** `require.php` moves from `^8.2` to `^8.4`.

  **What you must do:** on PHP 8.4 or newer, nothing. On 8.2, either upgrade PHP first or stay on the previous release — it keeps working and is unaffected by this.

- **BREAKING — Laravel 11 and 12 are no longer supported.** The framework requirement narrows from `^11.0|^12.0|^13.0` to `^13.0`.

  **What you must do:** on Laravel 13, nothing. On 11 or 12, stay on the previous release until you upgrade the framework.

- CI now tests PHP 8.4 with Laravel 13 only, instead of a matrix spanning versions this package no longer claims to support. A matrix that tests what the manifest forbids is worse than none — it reports green for a combination nobody can install.

### Why

These are the kit 0.5 platform floors. The suite was split across PHP 8.2 and 8.3 with the framework spanning 11–13, so no package could rely on anything newer than its weakest sibling. Every PHP package in the kit takes the same floors at once, so a consumer never has to resolve a mix.

Pre-1.0, so this lands in a MINOR. **No API changed, nothing was removed, nothing was renamed** — only what the package requires.


## 0.4.0 — 2026-07-07

### Added

- og:image:type support (image_type config + imageType payload key)

## 0.3.0 — 2026-06-24

### Added

- sitemap leak-guard + x-files delegation guidance

## 0.2.1 — 2026-06-15

### Fixed

- **validate:** skip noindex routes — they're not search-facing

## 0.2.0 — 2026-06-14

### Changed

- Add HowTo JSON-LD, og:image alt/dims, CSP nonce, and seo:validate command

## 0.1.0 — 2026-06-14

### Added

- initial release — server-rendered SEO + crawlability for Laravel + Inertia
