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
| `/sitemap.xml` | every URL you register via `FancySeo::sitemap(...)` |
| `/robots.txt` | crawl policy — welcomes LLM bots, references the sitemap |
| `/llms.txt`, `/llms-full.txt` | the [llmstxt.org](https://llmstxt.org) Markdown index you register via `FancySeo::llms(...)` |
| `/.well-known/security.txt` | RFC 9116 (only when a contact is configured) |
| `/humans.txt` | colophon |
| `/{path}.md` | per-page raw Markdown (opt-in) via `FancySeo::markdownUsing(...)` |

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
`breadcrumbList`, `faqPage`, `collectionPage`. Each returns a plain array with the
`@context`/`@type` set; pass them to `defaults()` / `route()` / `for()`.

## License

MIT

---

## ⭐ Star Fancy UI

If this package is useful to you, a quick ⭐ on the repo really helps us build a
better kit. Thank you!
