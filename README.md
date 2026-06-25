<p align="left"><img src="./art/fancy-ui.svg" alt="Fancy UI" height="28"></p>

# particle-academy/fancy-seo

**Server-rendered SEO + crawlability for Laravel + Inertia apps.**

An Inertia/React SPA applies per-page `<Head>` meta *after* hydration — so a
crawler, social scraper, or LLM bot that hits a URL sees only the bare root view
on the first byte. `fancy-seo` computes the per-route head (title, description,
canonical, Open Graph, Twitter, JSON-LD) on the **server** and renders it into
the first byte, plus a dynamic `sitemap.xml` / `robots.txt` / `llms.txt` and an
optional per-page Markdown variant.

It's the PHP baseline that pairs with
[`@particle-academy/fancy-inertia/seo`](https://www.npmjs.com/package/@particle-academy/fancy-inertia)'s
client `<Seo>` component: the package renders the default head; `<Seo>` overrides
it per page via Inertia's `head-key` dedupe.

## Install

```bash
composer require particle-academy/fancy-seo
```

Laravel auto-discovers the service provider + `FancySeo` facade. Publish the
config if you want to tune it:

```bash
php artisan vendor:publish --tag=fancy-seo-config
```

## Wire it up

**1. Drop the head component into your root Blade template** (e.g. `app.blade.php`),
inside `<head>`:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-fancy-seo::head />

    @vite(['resources/js/app.tsx'])
    @inertiaHead
</head>
```

That renders `<title>`, description, canonical, robots, the full Open Graph +
Twitter set, the `llms.txt` alternate link, and one `<script type="application/ld+json">`
per JSON-LD node — all from the resolved per-route payload.

**2. Register defaults + per-route SEO** from a service provider:

```php
use FancySeo\Facades\FancySeo;
use FancySeo\JsonLd;

FancySeo::defaults([
    'site_name' => 'Fancy UI',
    'image' => '/og/default.png',
    'twitterSite' => '@particleacademy',
    'jsonLd' => [
        JsonLd::website('Fancy UI', url('/'), searchUrlTemplate: url('/search?q={search_term_string}')),
        JsonLd::softwareApplication('Fancy UI', url('/'), ['applicationCategory' => 'DeveloperApplication', 'operatingSystem' => 'Web', 'price' => '0']),
    ],
]);

FancySeo::route('packages.show', fn (array $params) => [
    'title' => "{$params['package']} — Fancy UI",
    'jsonLd' => [JsonLd::softwareSourceCode($params['package'], url("/packages/{$params['package']}"), "https://github.com/Particle-Academy/{$params['package']}", ['programmingLanguage' => 'TypeScript'])],
]);

FancySeo::noindexRoutes(['admin.*', 'auth.*']);
```

A controller can also set the payload for the current request:

```php
FancySeo::for(['title' => $post->title, 'description' => $post->excerpt, 'type' => 'article']);
```

Precedence: `defaults()` → per-route resolver → `resolveUsing()` → `for()`. `jsonLd`
**accumulates** across layers; everything else overrides.

## Discovery endpoints

Auto-registered (toggle each in `config/fancy-seo.php`):

| Route | What |
|---|---|
| `/sitemap.xml` | every URL you register via `FancySeo::sitemap(...)` — paths in `robots_txt.disallow` are auto-excluded (a sitemap must never advertise a blocked path) |
| `/robots.txt` | crawl policy — welcomes LLM bots (each gets its own group with the disallow set re-applied, so a private path can't leak), references the sitemap |
| `/llms.txt`, `/llms-full.txt` | the [llmstxt.org](https://llmstxt.org) Markdown index you register via `FancySeo::llms(...)` |
| `/.well-known/security.txt` | RFC 9116 (only when a contact is configured) |
| `/humans.txt` | colophon |
| `/{path}.md` | per-page raw Markdown (opt-in) via `FancySeo::markdownUsing(...)` |

> **Well-known files vs. SEO meta.** fancy-seo's job is the per-route SEO
> `<head>` + JSON-LD + the dynamic sitemap *data*. For richer, leak-proof
> management of the *files* — robots / sitemap / security / humans / llms /
> AGENTS with an admin-editable model and a `protect()` rail that keeps private
> paths disallowed for **every** bot group — pair it with
> [`particle-academy/fancy-x-files`](https://github.com/Particle-Academy/fancy-x-files):
> turn off the routes you delegate (`config/fancy-seo.php` → `routes`) and let
> x-files own them, feeding it `FancySeo::sitemapUrls()` for a dynamic, leak-safe
> sitemap. That is the recommended pairing — fancy-seo for meta, x-files for files.

```php
FancySeo::sitemap(function ($map) {
    $map->add('/', '1.0', 'daily');
    foreach (Package::all() as $pkg) {
        $map->add("packages/{$pkg->slug}", '0.8');
    }
});

FancySeo::llms(fn (FancySeo $seo) => view('seo.llms')->render());

FancySeo::markdownUsing(fn (string $path) => MarkdownContent::for($path)); // null → 404
```

## JSON-LD builders

`FancySeo\JsonLd` mirrors `@particle-academy/fancy-inertia/seo`'s builders —
`website`, `organization`, `softwareApplication`, `softwareSourceCode`, `article`,
`breadcrumbList`, `faqPage`, `howTo`, `collectionPage`. Each returns a plain array
with the `@context`/`@type` set; pass them to `defaults()` / `route()` / `for()`.

`howTo()` and `faqPage()` no longer render as Google rich results, but the markup
still strengthens machine understanding + AI answers — emit them only on pages
whose **visible** content genuinely is an ordered how-to / Q&A.

## Social images

Set richer Open Graph / Twitter card metadata via the resolved payload (or the
`image_alt` / `image_width` / `image_height` config defaults):

```php
FancySeo::for([
    'image' => '/og/react-fancy.png',
    'imageAlt' => 'react-fancy — Tailwind v4 React primitives',
    'imageWidth' => 1200,
    'imageHeight' => 630,
]);
```

The head component emits `og:image:alt|width|height` + `twitter:image:alt`.

## Content Security Policy

Under a strict `script-src 'nonce-…'` CSP, pass the nonce so the inline JSON-LD
isn't dropped: `<x-fancy-seo::head :nonce="$cspNonce" />` (or set a static
`fancy-seo.csp_nonce` in config).

## Validate in CI

`php artisan fancy-seo:validate` lints every parameter-less, named GET route the
way `<x-fancy-seo::head>` renders it — missing/duplicate titles, thin or
over-long descriptions, missing/relative canonicals, noindex leaks, and malformed
JSON-LD. Use `--format=json|junit` for machine output and `--strict` to fail on
warnings:

```yaml
- run: php artisan fancy-seo:validate --format=junit --strict
```

## Roadmap

Not yet shipped (open an issue if you need them sooner):

- **hreflang** locale clusters + reciprocal `x-default` (for multilingual sites).
- **Sitemap index / chunking** for sites above the 50k-URL per-file limit.

## License

MIT

---

## ⭐ Star Fancy UI

If this package is useful to you, a quick ⭐ on the repo really helps us build a
better kit. Thank you!
