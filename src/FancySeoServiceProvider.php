<?php

namespace FancySeo;

use FancySeo\Console\ValidateCommand;
use FancySeo\Http\Controllers\MarkdownController;
use FancySeo\Http\Controllers\SeoController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FancySeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fancy-seo.php', 'fancy-seo');

        // One instance per request so a controller's FancySeo::for([...]) sticks
        // for the rest of that request.
        $this->app->scoped(FancySeo::class, fn () => new FancySeo);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fancy-seo');
        Blade::anonymousComponentNamespace('fancy-seo::components', 'fancy-seo');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([ValidateCommand::class]);

            $this->publishes([
                __DIR__.'/../config/fancy-seo.php' => config_path('fancy-seo.php'),
            ], 'fancy-seo-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fancy-seo'),
            ], 'fancy-seo-views');
        }
    }

    protected function registerRoutes(): void
    {
        if (! config('fancy-seo.routes.enabled', true)) {
            return;
        }

        Route::group([], function (): void {
            if (config('fancy-seo.routes.sitemap', true)) {
                Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('fancy-seo.sitemap');
            }
            if (config('fancy-seo.routes.robots', true)) {
                Route::get('robots.txt', [SeoController::class, 'robots'])->name('fancy-seo.robots');
            }
            if (config('fancy-seo.routes.llms', true)) {
                Route::get('llms.txt', [SeoController::class, 'llms'])->name('fancy-seo.llms');
                Route::get('llms-full.txt', [SeoController::class, 'llmsFull'])->name('fancy-seo.llms-full');
            }
            if (config('fancy-seo.routes.security', true)) {
                Route::get('.well-known/security.txt', [SeoController::class, 'security'])->name('fancy-seo.security');
                Route::get('security.txt', [SeoController::class, 'security']);
            }
            if (config('fancy-seo.routes.humans', true)) {
                Route::get('humans.txt', [SeoController::class, 'humans'])->name('fancy-seo.humans');
            }
            // Per-page markdown. Opt-in: needs a renderer via FancySeo::markdownUsing().
            if (config('fancy-seo.routes.markdown', false)) {
                $markdown = Route::get('{path}.md', MarkdownController::class)
                    ->where('path', '.*')
                    ->name('fancy-seo.markdown');

                // This provider boots BEFORE the host's routes/web.php loads, so
                // being registered last among our routes does not put the
                // catch-all after the host's. Once every route exists, step
                // aside for the host's own `.md` routes.
                $this->app->booted(static function () use ($markdown): void {
                    self::stepAsideForHostMarkdownRoutes($markdown);
                });
            }
        });
    }

    /**
     * Narrow the `{path}.md` catch-all so it no longer matches a URL that one of
     * the host's GET routes ending in `.md` matches, static (`about.md`) or
     * parameterised (`docs/{page}.md`). Everything else still reaches it,
     * including pages behind a host route like `blog/{slug}`, which is what the
     * catch-all is for.
     *
     * Done with a `where` on the route rather than by re-ordering, so it holds
     * in `route:cache` too: the cache compiles the route's requirement as it is
     * after boot.
     */
    public static function stepAsideForHostMarkdownRoutes(RoutingRoute $markdown): void
    {
        $patterns = [];
        foreach (Route::getRoutes()->get('GET') as $route) {
            $uri = $route->uri();
            if ($route === $markdown || $route->getName() === 'fancy-seo.markdown' || ! str_ends_with($uri, '.md')) {
                continue;
            }
            $patterns[] = self::uriPattern(substr($uri, 0, -3), $route->wheres);
        }

        if ($patterns !== []) {
            $markdown->where('path', '(?!(?:'.implode('|', array_unique($patterns)).')[.]md$).*');
        }
    }

    /**
     * A route URI (without its `.md`) as a regex fragment: literals quoted,
     * `{name}` as the route's own `where` for it, else one path segment.
     *
     * @param  array<string,string>  $wheres
     */
    private static function uriPattern(string $uri, array $wheres): string
    {
        $parts = preg_split('/(\{\w+\??\})/', $uri, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $pattern = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)(\?)?\}$/', $part, $m) === 1) {
                $segment = '(?:'.($wheres[$m[1]] ?? '[^/]+').')';
                $pattern .= ($m[2] ?? '') === '?' ? $segment.'?' : $segment;
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return $pattern;
    }
}
