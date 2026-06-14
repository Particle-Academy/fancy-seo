<?php

namespace FancySeo;

use Illuminate\Support\Str;

/**
 * Collects sitemap URLs from registered providers. Paths are resolved against
 * the configured base URL; absolute URLs pass through untouched.
 */
class SitemapBuilder
{
    /** @var list<array{loc:string,priority:string,changefreq:string,lastmod:?string}> */
    protected array $urls = [];

    public function __construct(protected string $base) {}

    /** Add one URL (path or absolute) with optional priority/changefreq/lastmod. */
    public function add(string $path, string $priority = '0.5', string $changefreq = 'weekly', ?string $lastmod = null): static
    {
        $loc = Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : $this->base.($path === '/' ? '/' : '/'.ltrim($path, '/'));

        $this->urls[] = compact('loc', 'priority', 'changefreq', 'lastmod');

        return $this;
    }

    /**
     * Add many paths at once.
     *
     * @param  iterable<string>  $paths
     */
    public function addMany(iterable $paths, string $priority = '0.5', string $changefreq = 'weekly'): static
    {
        foreach ($paths as $path) {
            $this->add($path, $priority, $changefreq);
        }

        return $this;
    }

    /** @return list<array{loc:string,priority:string,changefreq:string,lastmod:?string}> */
    public function all(): array
    {
        return $this->urls;
    }

    /** Render the collected URLs as a sitemap XML document. */
    public function toXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($this->urls as $u) {
            $xml .= '  <url>'
                .'<loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'
                .($u['lastmod'] ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '')
                .'<changefreq>'.$u['changefreq'].'</changefreq>'
                .'<priority>'.$u['priority'].'</priority>'
                .'</url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
