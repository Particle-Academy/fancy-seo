<?php

namespace FancySeo\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Support\Facades\Route;

/**
 * A host app the way Laravel builds one: the package's provider boots first
 * and the host's routes (its routes/web.php) load afterwards, through
 * `withRouting()`. The per-page markdown route is switched on before boot.
 */
class HostTestCase extends TestCase
{
    protected function configure(string $base): ApplicationBuilder
    {
        return parent::configure($base)->withRouting(using: static function (): void {
            Route::middleware('web')->group(static function (): void {
                // A static host route that ends in .md.
                Route::get('about.md', static fn () => response('host about', 200));
                // A parameterised host route that ends in .md.
                Route::get('docs/{page}.md', static fn (string $page) => response("host docs {$page}", 200));
                // A parameterised host .md route with its own constraint.
                Route::get('files/{name}.md', static fn (string $name) => response("host file {$name}", 200))
                    ->where('name', '[a-z]+');
                // A host page route whose parameter would also match "x.md".
                Route::get('blog/{slug}', static fn (string $slug) => response("host blog {$slug}", 200));
                Route::get('/', static fn () => response('home', 200));
            });
        });
    }

    protected function defineEnvironment(Application $app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('fancy-seo.routes.markdown', true);
    }
}
