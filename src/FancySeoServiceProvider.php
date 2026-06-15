<?php

namespace FancySeo;

use FancySeo\Console\ValidateCommand;
use FancySeo\Http\Controllers\MarkdownController;
use FancySeo\Http\Controllers\SeoController;
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
            // Per-page markdown: register LAST so the `.md` catch-all can't shadow
            // real routes. Opt-in — needs a renderer via FancySeo::markdownUsing().
            if (config('fancy-seo.routes.markdown', false)) {
                Route::get('{path}.md', MarkdownController::class)
                    ->where('path', '.*')
                    ->name('fancy-seo.markdown');
            }
        });
    }
}
