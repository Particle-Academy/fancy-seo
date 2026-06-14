<?php

use FancySeo\FancySeo;
use FancySeo\JsonLd;
use Illuminate\Http\Request;

function seo(): FancySeo
{
    return app(FancySeo::class);
}

it('falls back to site-name title + indexable robots', function () {
    $data = seo()->forRequest(Request::create('https://example.test/anything'));

    expect($data->title)->toBe('Example')
        ->and($data->siteName)->toBe('Example')
        ->and($data->robots)->toContain('index, follow')
        ->and($data->canonical)->toBe('https://example.test/anything')
        ->and($data->type)->toBe('website');
});

it('uses defaults() for the baseline', function () {
    seo()->defaults([
        'title' => 'Home — Example',
        'description' => 'The tagline.',
        'image' => '/og.png',
        'jsonLd' => [JsonLd::website('Example', 'https://example.test/')],
    ]);

    $data = seo()->forRequest(Request::create('https://example.test/'));

    expect($data->title)->toBe('Home — Example')
        ->and($data->description)->toBe('The tagline.')
        ->and($data->image)->toBe('https://example.test/og.png') // root-relative → absolute
        ->and($data->canonical)->toBe('https://example.test/')
        ->and($data->jsonLd)->toHaveCount(1)
        ->and($data->jsonLd[0]['@type'])->toBe('WebSite');
});

it('for() overrides per request and accumulates jsonLd', function () {
    seo()->defaults(['jsonLd' => [JsonLd::website('Example', 'https://example.test/')]]);
    seo()->for([
        'title' => 'Override',
        'jsonLd' => [JsonLd::breadcrumbList([['name' => 'Home', 'url' => '/']])],
    ]);

    $data = seo()->forRequest(Request::create('https://example.test/x'));

    expect($data->title)->toBe('Override')
        ->and($data->jsonLd)->toHaveCount(2) // baseline + per-page
        ->and($data->jsonLd[1]['@type'])->toBe('BreadcrumbList');
});

it('noindex via for() flips robots', function () {
    seo()->for(['noindex' => true]);
    $data = seo()->forRequest(Request::create('https://example.test/admin'));

    expect($data->robots)->toBe('noindex, nofollow');
});

it('strips query strings + trailing slash from the canonical', function () {
    $data = seo()->forRequest(Request::create('https://example.test/packages/react-fancy/?sort=asc'));

    expect($data->canonical)->toBe('https://example.test/packages/react-fancy');
});

it('keeps absolute image URLs untouched', function () {
    seo()->for(['image' => 'https://cdn.test/card.png']);
    $data = seo()->forRequest(Request::create('https://example.test/'));

    expect($data->image)->toBe('https://cdn.test/card.png');
});
