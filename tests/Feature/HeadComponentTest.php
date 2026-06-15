<?php

use FancySeo\FancySeo;
use FancySeo\JsonLd;
use Illuminate\Http\Request;

it('renders the full server-side head block', function () {
    app(FancySeo::class)->for([
        'title' => 'react-fancy — Example',
        'description' => 'Tailwind v4 React primitives',
        'image' => '/og/react-fancy.png',
        'twitterSite' => '@particleacademy',
        'jsonLd' => [JsonLd::softwareSourceCode('react-fancy', 'https://example.test/p', 'https://github.com/x/react-fancy', ['programmingLanguage' => 'TypeScript'])],
    ]);
    $data = app(FancySeo::class)->forRequest(Request::create('https://example.test/packages/react-fancy'));

    $html = $this->blade('<x-fancy-seo::head :seo="$seo" />', ['seo' => $data]);

    $html->assertSee('<title inertia>react-fancy — Example</title>', false);
    $html->assertSee('name="description" content="Tailwind v4 React primitives"', false);
    $html->assertSee('rel="canonical" href="https://example.test/packages/react-fancy"', false);
    $html->assertSee('property="og:title" content="react-fancy — Example"', false);
    $html->assertSee('property="og:image" content="https://example.test/og/react-fancy.png"', false);
    $html->assertSee('name="twitter:card" content="summary_large_image"', false);
    $html->assertSee('name="twitter:site" content="@particleacademy"', false);
    $html->assertSee('name="robots" content="index, follow, max-image-preview:large"', false);
    $html->assertSee('application/ld+json', false);
    $html->assertSee('SoftwareSourceCode', false);
});

it('renders og/twitter image alt + dimensions and a CSP nonce', function () {
    app(FancySeo::class)->for([
        'title' => 'Card page',
        'image' => '/og/card.png',
        'imageAlt' => 'A Fancy UI social card',
        'imageWidth' => 1200,
        'imageHeight' => 630,
        'jsonLd' => [JsonLd::website('Example', 'https://example.test/')],
    ]);
    $data = app(FancySeo::class)->forRequest(Request::create('https://example.test/card'));

    $html = $this->blade('<x-fancy-seo::head :seo="$seo" nonce="abc123" />', ['seo' => $data]);

    $html->assertSee('property="og:image:alt" content="A Fancy UI social card"', false);
    $html->assertSee('property="og:image:width" content="1200"', false);
    $html->assertSee('property="og:image:height" content="630"', false);
    $html->assertSee('name="twitter:image:alt" content="A Fancy UI social card"', false);
    $html->assertSee('application/ld+json" nonce="abc123"', false);
});

it('renders noindex robots for a noindex page', function () {
    app(FancySeo::class)->for(['noindex' => true, 'title' => 'Admin']);
    $data = app(FancySeo::class)->forRequest(Request::create('https://example.test/admin'));

    $html = $this->blade('<x-fancy-seo::head :seo="$seo" />', ['seo' => $data]);

    $html->assertSee('name="robots" content="noindex, nofollow"', false);
});
