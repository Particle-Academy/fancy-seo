<?php

use FancySeo\FancySeo;
use Illuminate\Routing\CompiledRouteCollection;

/*
| The opt-in `{path}.md` route is registered while the package boots, before
| the host's routes/web.php. It must not take a URL the host routes itself,
| and it must keep serving Markdown for every other page, including pages
| behind a parameterised host route.
*/

beforeEach(function () {
    app(FancySeo::class)->markdownUsing(fn (string $path) => match ($path) {
        '/packages/react-fancy' => "# react-fancy\n",
        '/blog/hello' => "# hello\n",
        '/about' => "# about from fancy-seo\n",
        '/docs/intro' => "# intro from fancy-seo\n",
        default => null,
    });
});

/** Swap the live route collection for its compiled form, as `route:cache` does. */
function useCompiledRoutes(): void
{
    $router = app('router');
    $compiled = $router->getRoutes()->compile();
    $router->setCompiledRoutes($compiled);

    expect($router->getRoutes())->toBeInstanceOf(CompiledRouteCollection::class);
}

function assertHostRoutesWin($t): void
{
    $t->get('/about.md')->assertOk()->assertContent('host about');
    $t->get('/docs/intro.md')->assertOk()->assertContent('host docs intro');
}

function assertMarkdownStillServed($t): void
{
    $page = $t->get('/packages/react-fancy.md');
    $page->assertOk();
    expect($page->headers->get('Content-Type'))->toContain('text/markdown');
    $page->assertSee('# react-fancy', false);

    // A page behind the host's parameterised `blog/{slug}` route still gets
    // its Markdown, as before.
    $t->get('/blog/hello.md')->assertOk()->assertSee('# hello', false);
    $t->get('/blog/hello')->assertOk()->assertContent('host blog hello');

    $t->get('/nope.md')->assertNotFound();
}

it('lets a static host .md route win over the catch-all', function () {
    $this->get('/about.md')->assertOk()->assertContent('host about');
});

it('lets a parameterised host .md route win over the catch-all', function () {
    $this->get('/docs/intro.md')->assertOk()->assertContent('host docs intro');
});

it('still serves per-page markdown for every other path', function () {
    assertMarkdownStillServed($this);
});

it('keeps both behaviours once the routes are compiled for route:cache', function () {
    useCompiledRoutes();

    assertHostRoutesWin($this);
    assertMarkdownStillServed($this);
});

it('does not exclude a near-miss of a host route', function () {
    // "about" is the host's; "about-us" and "x/about" are not.
    app(FancySeo::class)->markdownUsing(fn (string $path) => "# {$path}\n");

    $this->get('/about-us.md')->assertOk()->assertSee('# /about-us', false);
    $this->get('/x/about.md')->assertOk()->assertSee('# /x/about', false);
    $this->get('/docs/a/b.md')->assertOk()->assertSee('# /docs/a/b', false);
});

it('honours the host route\'s own parameter constraint', function () {
    app(FancySeo::class)->markdownUsing(fn (string $path) => "# {$path}\n");

    // files/{name}.md only takes [a-z]+, so "abc1" is not the host's.
    $this->get('/files/abc.md')->assertOk()->assertContent('host file abc');
    $this->get('/files/abc1.md')->assertOk()->assertSee('# /files/abc1', false);

    useCompiledRoutes();
    $this->get('/files/abc.md')->assertOk()->assertContent('host file abc');
    $this->get('/files/abc1.md')->assertOk()->assertSee('# /files/abc1', false);
});
