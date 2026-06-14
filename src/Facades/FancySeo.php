<?php

namespace FancySeo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FancySeo\FancySeo defaults(array $defaults)
 * @method static \FancySeo\FancySeo route(string $name, \Closure|array $resolver)
 * @method static \FancySeo\FancySeo resolveUsing(\Closure $resolver)
 * @method static \FancySeo\FancySeo noindexRoutes(array $patterns)
 * @method static \FancySeo\FancySeo for(array $seo)
 * @method static \FancySeo\FancySeo sitemap(\Closure $provider)
 * @method static \FancySeo\FancySeo llms(\Closure $builder, bool $full = false)
 * @method static \FancySeo\FancySeo markdownUsing(\Closure $renderer)
 * @method static \FancySeo\SeoData forRequest(\Illuminate\Http\Request $request)
 * @method static array sitemapUrls()
 * @method static ?string renderLlms(bool $full = false)
 * @method static ?string renderMarkdown(string $path)
 * @method static string baseUrl()
 *
 * @see \FancySeo\FancySeo
 */
class FancySeo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FancySeo\FancySeo::class;
    }
}
