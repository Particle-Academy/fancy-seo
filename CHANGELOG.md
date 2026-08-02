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

## [Unreleased]

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
