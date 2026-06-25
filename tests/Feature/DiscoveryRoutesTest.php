<?php

use FancySeo\FancySeo;
use FancySeo\Http\Controllers\MarkdownController;
use Illuminate\Support\Facades\Route;

it('serves sitemap.xml with registered URLs', function () {
    app(FancySeo::class)->sitemap(function ($map): void {
        $map->add('/', '1.0', 'daily');
        $map->add('packages', '0.9');
        $map->add('packages/react-fancy', '0.8');
    });

    $res = $this->get('/sitemap.xml');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/xml');
    $res->assertSee('<loc>https://example.test/</loc>', false);
    $res->assertSee('<loc>https://example.test/packages/react-fancy</loc>', false);
    $res->assertSee('<priority>1.0</priority>', false);
});

it('omits robots-disallowed paths from the sitemap (leak-safety)', function () {
    config()->set('fancy-seo.robots_txt.disallow', ['/admin']);
    app(FancySeo::class)->sitemap(function ($map): void {
        $map->add('/');
        $map->add('packages');
        $map->add('admin/secret');
    });

    $body = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)
        ->toContain('<loc>https://example.test/packages</loc>') // public URL stays
        ->not->toContain('/admin/secret');                      // disallowed path never advertised
});

it('serves robots.txt welcoming AI bots + referencing the sitemap', function () {
    config()->set('fancy-seo.robots_txt.disallow', ['/admin']);
    config()->set('fancy-seo.robots_txt.ai_bots', ['ClaudeBot', 'GPTBot']);

    $res = $this->get('/robots.txt');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/plain');
    $res->assertSee('User-agent: *', false);
    $res->assertSee('Disallow: /admin', false);
    $res->assertSee('User-agent: ClaudeBot', false);
    $res->assertSee('Sitemap: https://example.test/sitemap.xml', false);
});

it('serves the registered llms.txt markdown', function () {
    app(FancySeo::class)->llms(fn (FancySeo $seo) => "# Example\n\n> index for LLMs at {$seo->baseUrl()}\n");

    $res = $this->get('/llms.txt');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/markdown');
    $res->assertSee('# Example', false);
    $res->assertSee('https://example.test', false);
});

it('404s llms.txt when no builder is registered', function () {
    $this->get('/llms.txt')->assertNotFound();
});

it('serves security.txt only when a contact is configured', function () {
    $this->get('/.well-known/security.txt')->assertNotFound();

    config()->set('fancy-seo.security_txt.contact', 'mailto:security@example.test');
    $res = $this->get('/.well-known/security.txt');

    $res->assertOk();
    $res->assertSee('Contact: mailto:security@example.test', false);
    $res->assertSee('Expires:', false);
});

it('renders per-page markdown via the registered renderer', function () {
    app(FancySeo::class)->markdownUsing(
        fn (string $path) => $path === '/packages/react-fancy' ? "# react-fancy\n" : null,
    );

    expect(app(FancySeo::class)->renderMarkdown('/packages/react-fancy'))->toBe("# react-fancy\n")
        ->and(app(FancySeo::class)->renderMarkdown('/nope'))->toBeNull();
});

it('serves the per-page markdown route when enabled at boot', function () {
    $this->app['config']->set('fancy-seo.routes.markdown', true);
    app(FancySeo::class)->markdownUsing(
        fn (string $path) => $path === '/packages/react-fancy' ? "# react-fancy\n" : null,
    );
    // Register only the markdown route for this assertion (boot already ran with
    // markdown off in the base env).
    Route::get('{path}.md', MarkdownController::class)
        ->where('path', '.*');

    $ok = $this->get('/packages/react-fancy.md');
    $ok->assertOk();
    expect($ok->headers->get('Content-Type'))->toContain('text/markdown');
    $ok->assertSee('# react-fancy', false);

    $this->get('/nope.md')->assertNotFound();
});
