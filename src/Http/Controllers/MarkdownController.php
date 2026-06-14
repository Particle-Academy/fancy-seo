<?php

namespace FancySeo\Http\Controllers;

use FancySeo\FancySeo;
use Illuminate\Http\Response;

/**
 * Serves the per-page raw Markdown variant — `GET {path}.md` — so LLM crawlers
 * (and humans) can fetch a page's content as clean Markdown instead of parsing
 * rendered HTML. The host registers the renderer via
 * `FancySeo::markdownUsing(fn (string $path) => …)`; a null return is a 404.
 */
class MarkdownController
{
    public function __construct(protected FancySeo $seo) {}

    public function __invoke(string $path): Response
    {
        $md = $this->seo->renderMarkdown('/'.ltrim($path, '/'));
        abort_if($md === null, 404);

        return response($md, 200)->header('Content-Type', 'text/markdown; charset=utf-8');
    }
}
