<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Site defaults
    |--------------------------------------------------------------------------
    | The baseline applied to every page. Per-route resolvers + a controller's
    | `FancySeo::for([...])` override these. `url` falls back to config('app.url').
    */
    'site_name' => env('FANCY_SEO_SITE_NAME', config('app.name', 'Laravel')),
    'url' => env('FANCY_SEO_URL', config('app.url')),
    'title' => null,            // default <title>; null → site_name
    'description' => null,      // default meta description
    'image' => null,           // default og/twitter image (absolute or root-relative)
    'locale' => 'en_US',
    'type' => 'website',        // default og:type
    'twitter_site' => null,     // @handle for twitter:site
    'theme_color' => null,      // <meta name="theme-color">

    /*
    |--------------------------------------------------------------------------
    | Indexing
    |--------------------------------------------------------------------------
    | `robots` is the default <meta name="robots"> for indexable pages.
    | `noindex_routes` are route-name patterns ('admin.*') always set noindex.
    */
    'robots' => 'index, follow, max-image-preview:large',
    'noindex_robots' => 'noindex, nofollow',
    'noindex_routes' => [],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Auto-register the discovery routes (sitemap.xml, robots.txt, llms.txt,
    | llms-full.txt, .well-known/security.txt, humans.txt). Disable any you
    | serve yourself. `markdown` enables the per-page `{path}.md` route.
    */
    'routes' => [
        'enabled' => true,
        'sitemap' => true,
        'robots' => true,
        'llms' => true,
        'security' => true,
        'humans' => true,
        'markdown' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    | Paths every user-agent (incl. the welcomed AI bots) must not crawl, and
    | the AI/LLM crawler user-agents explicitly allowed (we WANT to be ingested).
    */
    'robots_txt' => [
        'disallow' => ['/admin', '/login', '/logout'],
        'ai_bots' => [
            'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web',
            'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended',
            'CCBot', 'Amazonbot', 'Meta-ExternalAgent', 'cohere-ai',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | well-known
    |--------------------------------------------------------------------------
    */
    'security_txt' => [
        'contact' => env('FANCY_SEO_SECURITY_CONTACT'),   // e.g. mailto:security@example.com
        'languages' => 'en',
    ],
];
