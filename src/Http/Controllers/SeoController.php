<?php

namespace FancySeo\Http\Controllers;

use FancySeo\FancySeo;
use Illuminate\Http\Response;

/**
 * Discovery / well-known endpoints, served dynamically so they stay in sync
 * with the registered providers + config and resolve absolute URLs from the
 * configured base (env-correct without hardcoding a domain):
 *
 *   /sitemap.xml                 every URL registered via FancySeo::sitemap(...)
 *   /robots.txt                  crawl policy (welcomes LLM bots) + sitemap ref
 *   /llms.txt /llms-full.txt     the registered llmstxt.org markdown index
 *   /.well-known/security.txt    RFC 9116 (when a contact is configured)
 *   /humans.txt                  colophon
 */
class SeoController
{
    public function __construct(protected FancySeo $seo) {}

    protected function text(string $body, string $contentType = 'text/plain; charset=utf-8'): Response
    {
        return response($body, 200)->header('Content-Type', $contentType);
    }

    public function sitemap(): Response
    {
        $base = $this->seo->baseUrl();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($this->seo->sitemapUrls() as $u) {
            $xml .= '  <url>'
                .'<loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'
                .(($u['lastmod'] ?? null) ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '')
                .'<changefreq>'.$u['changefreq'].'</changefreq>'
                .'<priority>'.$u['priority'].'</priority>'
                .'</url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        // A sitemap with no registered providers still validates (empty urlset).
        unset($base);

        return $this->text($xml, 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        $base = $this->seo->baseUrl();
        $disallow = (array) config('fancy-seo.robots_txt.disallow', []);
        $aiBots = (array) config('fancy-seo.robots_txt.ai_bots', []);

        $lines = [
            '# '.config('fancy-seo.site_name').' — robots.txt',
            '',
            'User-agent: *',
            'Allow: /',
        ];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';

        foreach ($aiBots as $bot) {
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Allow: /';
            foreach ($disallow as $path) {
                $lines[] = "Disallow: {$path}";
            }
            $lines[] = '';
        }

        $lines[] = "Sitemap: {$base}/sitemap.xml";
        if ($this->seo->renderLlms(false) !== null) {
            $lines[] = "# LLM index: {$base}/llms.txt";
        }

        return $this->text(implode("\n", $lines)."\n");
    }

    public function llms(): Response
    {
        return $this->llmsResponse(false);
    }

    public function llmsFull(): Response
    {
        return $this->llmsResponse(true);
    }

    protected function llmsResponse(bool $full): Response
    {
        $md = $this->seo->renderLlms($full);
        if ($md === null) {
            return $this->text("# Not found\n", 'text/markdown; charset=utf-8')->setStatusCode(404);
        }

        return $this->text($md, 'text/markdown; charset=utf-8');
    }

    public function security(): Response
    {
        $contact = config('fancy-seo.security_txt.contact');
        if (! $contact) {
            abort(404);
        }
        $base = $this->seo->baseUrl();
        $expires = now()->addYear()->startOfDay()->toAtomString();
        $lines = [
            "Contact: {$contact}",
            "Expires: {$expires}",
            'Preferred-Languages: '.config('fancy-seo.security_txt.languages', 'en'),
            "Canonical: {$base}/.well-known/security.txt",
        ];

        return $this->text(implode("\n", $lines)."\n");
    }

    public function humans(): Response
    {
        $base = $this->seo->baseUrl();
        $name = config('fancy-seo.site_name');
        $lines = [
            '/* humans.txt */',
            '',
            '# SITE',
            "{$name} — {$base}",
        ];

        return $this->text(implode("\n", $lines)."\n");
    }
}
