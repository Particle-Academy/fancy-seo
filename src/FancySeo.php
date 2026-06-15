<?php

namespace FancySeo;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The server-rendered SEO baseline for a Laravel + Inertia app.
 *
 * An Inertia/React SPA applies per-page `<Head>` meta AFTER hydration — so a
 * crawler / social scraper / LLM bot sees only the bare root view on the first
 * byte. This service computes the per-route head (title / description /
 * canonical / Open Graph / Twitter / JSON-LD) on the SERVER and the
 * `<x-fancy-seo::head>` component renders it into the first byte. The client
 * `<Seo>` from `@particle-academy/fancy-inertia/seo` layers per-page overrides
 * on top via Inertia's `head-key` dedupe.
 *
 * Register defaults + per-route resolvers from a service provider; a controller
 * can also set an explicit payload for the current request via {@see for()}.
 */
class FancySeo
{
    /** @var array<string,mixed> */
    protected array $defaults = [];

    /** @var array<string, Closure|array<string,mixed>> route name => resolver */
    protected array $routeResolvers = [];

    /** @var list<Closure> global resolvers (Request → partial array) */
    protected array $resolvers = [];

    /** @var array<string,mixed>|null per-request override */
    protected ?array $override = null;

    /** @var list<string> route-name glob patterns forced to noindex */
    protected array $noindexRoutes = [];

    /** @var list<Closure> sitemap url providers (SitemapBuilder → void) */
    protected array $sitemapProviders = [];

    /** @var array<string, Closure> 'index'|'full' => (FancySeo → string markdown) */
    protected array $llmsBuilders = [];

    protected ?Closure $markdownRenderer = null;

    public function __construct()
    {
        $this->defaults = array_filter([
            'site_name' => config('fancy-seo.site_name'),
            'url' => rtrim((string) config('fancy-seo.url'), '/'),
            'title' => config('fancy-seo.title'),
            'description' => config('fancy-seo.description'),
            'image' => config('fancy-seo.image'),
            'locale' => config('fancy-seo.locale'),
            'type' => config('fancy-seo.type'),
            'twitterSite' => config('fancy-seo.twitter_site'),
            'themeColor' => config('fancy-seo.theme_color'),
            'robots' => config('fancy-seo.robots'),
            'jsonLd' => [],
            'keywords' => [],
        ], fn ($v) => $v !== null);
        $this->noindexRoutes = (array) config('fancy-seo.noindex_routes', []);
    }

    // ── Registration ───────────────────────────────────────────────────────

    /** Merge baseline defaults (site_name, image, jsonLd, …). @param array<string,mixed> $defaults */
    public function defaults(array $defaults): static
    {
        $this->defaults = array_replace($this->defaults, $defaults);

        return $this;
    }

    /**
     * Register a per-route-name resolver. The resolver receives the route
     * parameters and returns a partial SEO array (any of title/description/
     * type/image/canonical/noindex/jsonLd/keywords/twitterCard).
     *
     * @param  Closure(array<string,mixed>):array<string,mixed>|array<string,mixed>  $resolver
     */
    public function route(string $name, Closure|array $resolver): static
    {
        $this->routeResolvers[$name] = $resolver;

        return $this;
    }

    /** Register a catch-all resolver: Request → partial SEO array. @param Closure(Request):array<string,mixed> $resolver */
    public function resolveUsing(Closure $resolver): static
    {
        $this->resolvers[] = $resolver;

        return $this;
    }

    /** Force these route-name patterns (e.g. `admin.*`) to noindex. @param list<string> $patterns */
    public function noindexRoutes(array $patterns): static
    {
        $this->noindexRoutes = [...$this->noindexRoutes, ...$patterns];

        return $this;
    }

    /** Explicitly set the SEO payload for the CURRENT request (highest precedence). @param array<string,mixed> $seo */
    public function for(array $seo): static
    {
        $this->override = array_replace($this->override ?? [], $seo);

        return $this;
    }

    /** Register a sitemap URL provider. @param Closure(SitemapBuilder):void $provider */
    public function sitemap(Closure $provider): static
    {
        $this->sitemapProviders[] = $provider;

        return $this;
    }

    /** Register the `/llms.txt` (and optionally `/llms-full.txt`) markdown builder. @param Closure(FancySeo):string $builder */
    public function llms(Closure $builder, bool $full = false): static
    {
        $this->llmsBuilders[$full ? 'full' : 'index'] = $builder;

        return $this;
    }

    /** Register the per-page `.md` renderer: a request path → markdown|null. @param Closure(string):?string $renderer */
    public function markdownUsing(Closure $renderer): static
    {
        $this->markdownRenderer = $renderer;

        return $this;
    }

    // ── Resolution ─────────────────────────────────────────────────────────

    /** Resolve the full SEO payload for a request. */
    public function forRequest(Request $request): SeoData
    {
        $route = $request->route();
        $name = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;
        $params = is_object($route) && method_exists($route, 'parameters') ? $route->parameters() : [];

        $data = $this->defaults;

        // Per-route-name resolver.
        if ($name !== null && isset($this->routeResolvers[$name])) {
            $resolver = $this->routeResolvers[$name];
            $partial = $resolver instanceof Closure ? $resolver($params) : $resolver;
            $data = $this->mergePartial($data, $partial);
        }

        // Catch-all resolvers.
        foreach ($this->resolvers as $resolver) {
            $data = $this->mergePartial($data, (array) $resolver($request));
        }

        // Per-request override.
        if ($this->override !== null) {
            $data = $this->mergePartial($data, $this->override);
        }

        $base = rtrim((string) ($data['url'] ?? config('app.url')), '/');
        $siteName = (string) ($data['site_name'] ?? config('app.name', ''));
        $title = (string) ($data['title'] ?? $siteName);
        $description = (string) ($data['description'] ?? '');

        $noindex = (bool) ($data['noindex'] ?? $this->routeIsNoindex($name));
        $robots = $noindex
            ? (string) config('fancy-seo.noindex_robots', 'noindex, nofollow')
            : (string) ($data['robots'] ?? config('fancy-seo.robots'));

        return new SeoData(
            title: $title,
            description: $description,
            canonical: (string) ($data['canonical'] ?? $this->canonical($request, $base)),
            image: $this->absoluteImage($data['image'] ?? null, $base),
            type: (string) ($data['type'] ?? 'website'),
            siteName: $siteName,
            locale: (string) ($data['locale'] ?? 'en_US'),
            robots: $robots,
            jsonLd: array_values((array) ($data['jsonLd'] ?? [])),
            keywords: array_values((array) ($data['keywords'] ?? [])),
            twitterSite: $data['twitterSite'] ?? null,
            themeColor: $data['themeColor'] ?? null,
            twitterCard: (string) ($data['twitterCard'] ?? 'summary_large_image'),
            imageAlt: $data['imageAlt'] ?? config('fancy-seo.image_alt'),
            imageWidth: isset($data['imageWidth']) ? (int) $data['imageWidth'] : config('fancy-seo.image_width'),
            imageHeight: isset($data['imageHeight']) ? (int) $data['imageHeight'] : config('fancy-seo.image_height'),
        );
    }

    /** @return list<array{loc:string,priority:string,changefreq:string,lastmod:?string}> */
    public function sitemapUrls(): array
    {
        $builder = new SitemapBuilder(rtrim((string) ($this->defaults['url'] ?? config('app.url')), '/'));
        foreach ($this->sitemapProviders as $provider) {
            $provider($builder);
        }

        return $builder->all();
    }

    public function renderLlms(bool $full = false): ?string
    {
        $builder = $this->llmsBuilders[$full ? 'full' : 'index'] ?? null;

        return $builder ? (string) $builder($this) : null;
    }

    public function renderMarkdown(string $path): ?string
    {
        return $this->markdownRenderer ? $this->markdownRenderer->__invoke($path) : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->defaults['url'] ?? config('app.url')), '/');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $partial
     * @return array<string,mixed>
     */
    protected function mergePartial(array $base, array $partial): array
    {
        // jsonLd accumulates (baseline + per-page); everything else overrides.
        if (isset($partial['jsonLd'])) {
            $partial['jsonLd'] = [...(array) ($base['jsonLd'] ?? []), ...(array) $partial['jsonLd']];
        }

        return array_replace($base, $partial);
    }

    protected function canonical(Request $request, string $base): string
    {
        $path = '/'.ltrim($request->path(), '/');

        return $path === '/' ? $base.'/' : $base.rtrim($path, '/');
    }

    protected function absoluteImage(?string $image, string $base): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }
        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        return $base.'/'.ltrim($image, '/');
    }

    protected function routeIsNoindex(?string $name): bool
    {
        if ($name === null) {
            return false;
        }
        foreach ($this->noindexRoutes as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
