<?php

use FancySeo\FancySeo;

/*
 * A sitemap provider that RETURNS its URLs instead of adding them.
 *
 * `sitemap()` takes a `Closure(SitemapBuilder):void` and the return value is
 * discarded, so a closure written as
 *
 *     ->sitemap(fn () => ['/', '/about'])          // WRONG
 *
 * instead of
 *
 *     ->sitemap(fn ($map) => $map->add('/')->add('/about'))
 *
 * contributed nothing — and `/sitemap.xml` then answered **200** with a valid,
 * empty urlset. No exception, no log line, no signal at any layer: a sitemap that
 * is silently empty looks exactly like a sitemap with nothing to list.
 *
 * Reported by the GuardCard team, who lost real time to it. Their words: a
 * documentation trap rather than a bug. I disagree on that one point and this
 * file is the disagreement — a README only helps whoever reads it, and nobody
 * reads one to find out why a working page is working.
 */

it('serves URLs a provider ADDS, which is the contract', function () {
    app(FancySeo::class)->sitemap(function ($map): void {
        $map->add('/');
        $map->add('about');
    });

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://example.test/about</loc>', false);
});

it('refuses a provider that RETURNS its urls and adds none', function () {
    // The exact shape GuardCard wrote. It is unambiguous: a provider cannot
    // mean anything by returning a list while contributing nothing.
    app(FancySeo::class)->sitemap(fn () => ['/', '/about', '/contact']);

    expect(fn () => app(FancySeo::class)->sitemapUrls())
        ->toThrow(LogicException::class);
});

it('says what the caller actually did wrong', function () {
    // An exception that only says "invalid provider" sends someone back to the
    // docs. This one names the mistake, the fix, and how many URLs were lost.
    app(FancySeo::class)->sitemap(fn () => ['/', '/about', '/contact']);

    try {
        app(FancySeo::class)->sitemapUrls();
        $this->fail('expected a LogicException');
    } catch (LogicException $e) {
        expect($e->getMessage())
            ->toContain('returned')
            ->toContain('3')          // the URLs that would have been dropped
            ->toContain('add(');      // what to call instead
    }
});

it('allows a provider that returns the BUILDER, which chaining produces', function () {
    // `$map->add(...)` returns `$this`, so `fn ($map) => $map->add('/')` returns
    // a SitemapBuilder. That is idiomatic and must not be mistaken for the bug —
    // the whole point of keying on "returned a list AND added nothing".
    app(FancySeo::class)->sitemap(fn ($map) => $map->add('/')->add('about'));

    expect(app(FancySeo::class)->sitemapUrls())->toHaveCount(2);
});

it('allows a provider that returns a list but also added urls', function () {
    // Deliberately NOT an error. The return is sloppy, but the provider did its
    // job and a working sitemap must not start throwing. Only the
    // contributed-nothing case is unambiguous, and firing on anything wider
    // would break live sitemaps to punish a style.
    app(FancySeo::class)->sitemap(function ($map) {
        $map->add('/');

        return ['/ignored'];
    });

    expect(app(FancySeo::class)->sitemapUrls())->toHaveCount(1);
});

it('allows a provider that adds nothing and returns nothing', function () {
    // An empty sitemap is a legitimate state — a provider gated on a feature
    // flag, or one whose query found no rows. It must stay legitimate, or this
    // check becomes the thing that breaks correct code.
    app(FancySeo::class)->sitemap(function ($map): void {
        // intentionally empty
    });

    expect(app(FancySeo::class)->sitemapUrls())->toBe([]);
});

it('names the provider position when several are registered', function () {
    // With five providers, "a provider returned its urls" is not actionable.
    app(FancySeo::class)->sitemap(function ($map): void {
        $map->add('/');
    });
    app(FancySeo::class)->sitemap(fn () => ['/about']);

    try {
        app(FancySeo::class)->sitemapUrls();
        $this->fail('expected a LogicException');
    } catch (LogicException $e) {
        expect($e->getMessage())->toContain('#2');
    }
});
