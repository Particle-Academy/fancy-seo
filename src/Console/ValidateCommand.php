<?php

namespace FancySeo\Console;

use FancySeo\FancySeo;
use FancySeo\SeoData;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lint the server-rendered SEO of every parameter-less, named GET route.
 *
 * It resolves each route through {@see FancySeo::forRequest()} — exactly what
 * `<x-fancy-seo::head>` renders — and flags the mistakes that quietly tank
 * rankings + AI citations: missing/duplicate titles, thin or over-long
 * descriptions, missing/relative canonicals, noindex leaks, and malformed
 * JSON-LD. Wire `--format=junit` into CI so regressions fail the build.
 */
class ValidateCommand extends Command
{
    protected $signature = 'fancy-seo:validate
        {--format=text : Output format — text, json, or junit}
        {--strict : Treat warnings as failures (non-zero exit)}';

    protected $description = 'Lint the server-rendered SEO head of every named GET route';

    /** Recommended title length window (chars). */
    private const TITLE_MIN = 10;

    private const TITLE_MAX = 60;

    /** Recommended meta-description length window (chars). */
    private const DESC_MIN = 50;

    private const DESC_MAX = 160;

    public function handle(FancySeo $seo): int
    {
        $base = $seo->baseUrl();

        /** @var list<array{route:string,uri:string,severity:string,message:string}> $findings */
        $findings = [];
        /** @var array<string,list<string>> $titles title => route names */
        $titles = [];

        foreach ($this->lintableRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = '/'.ltrim($route->uri(), '/');

            try {
                $request = Request::create($base.$uri);
                $route->bind($request);
                $request->setRouteResolver(fn () => $route);
                $data = $seo->forRequest($request);
            } catch (Throwable $e) {
                $findings[] = ['route' => $name, 'uri' => $uri, 'severity' => 'error', 'message' => 'Resolver threw: '.$e->getMessage()];

                continue;
            }

            // Skip noindex routes entirely — they're explicitly excluded from
            // search, so a missing/duplicate title or description is intentional,
            // not a defect (admin, auth, checkout, …).
            if (Str::contains($data->robots, 'noindex')) {
                continue;
            }

            foreach ($this->lintData($data) as [$severity, $message]) {
                $findings[] = ['route' => $name, 'uri' => $uri, 'severity' => $severity, 'message' => $message];
            }

            $titles[$data->title][] = $name;
        }

        foreach ($titles as $title => $routes) {
            if ($title !== '' && count($routes) > 1) {
                foreach ($routes as $routeName) {
                    $findings[] = ['route' => $routeName, 'uri' => '', 'severity' => 'error', 'message' => "Duplicate <title> \"{$title}\" shared by ".count($routes).' routes'];
                }
            }
        }

        return $this->report($findings);
    }

    /**
     * Named GET routes that render an HTML page — excludes the package's own
     * discovery endpoints, asset-like paths, and routes with required params
     * (we can't synthesise their bindings).
     *
     * @return list<IlluminateRoute>
     */
    private function lintableRoutes(): array
    {
        $routes = [];
        foreach ($this->getLaravel()['router']->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (Str::startsWith($name, 'fancy-seo.')) {
                continue;
            }
            $uri = $route->uri();
            if (Str::contains($uri, '{') || Str::endsWith($uri, ['.xml', '.txt', '.md'])) {
                continue;
            }
            // Skip framework/dev routes (_workbench, _ignition, _debugbar, …).
            if (Str::startsWith($uri, '_')) {
                continue;
            }
            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * @return list<array{0:string,1:string}> [severity, message] pairs
     */
    private function lintData(SeoData $data): array
    {
        $out = [];
        $indexable = ! Str::contains($data->robots, 'noindex');

        if ($data->title === '') {
            $out[] = ['error', 'Missing <title>'];
        } else {
            $len = Str::length($data->title);
            if ($len > self::TITLE_MAX) {
                $out[] = ['warning', "Title is {$len} chars (over ".self::TITLE_MAX.' — may truncate in SERPs)'];
            } elseif ($len < self::TITLE_MIN) {
                $out[] = ['warning', "Title is only {$len} chars (under ".self::TITLE_MIN.')'];
            }
        }

        if ($data->description === '') {
            if ($indexable) {
                $out[] = ['warning', 'Missing meta description'];
            }
        } else {
            $len = Str::length($data->description);
            if ($len > self::DESC_MAX) {
                $out[] = ['warning', "Description is {$len} chars (over ".self::DESC_MAX.')'];
            } elseif ($len < self::DESC_MIN) {
                $out[] = ['warning', "Description is only {$len} chars (under ".self::DESC_MIN.')'];
            }
        }

        if ($data->canonical === '') {
            $out[] = ['error', 'Missing canonical URL'];
        } elseif (! Str::startsWith($data->canonical, ['http://', 'https://'])) {
            $out[] = ['error', "Canonical is not absolute: {$data->canonical}"];
        }

        foreach ($data->jsonLd as $i => $node) {
            if (! is_array($node) || ! isset($node['@context'], $node['@type'])) {
                $out[] = ['error', "JSON-LD node #{$i} is missing @context or @type"];

                continue;
            }
            if (json_encode($node) === false) {
                $out[] = ['error', "JSON-LD node #{$i} (@type {$node['@type']}) is not JSON-serialisable"];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{route:string,uri:string,severity:string,message:string}>  $findings
     */
    private function report(array $findings): int
    {
        $errors = array_filter($findings, fn ($f) => $f['severity'] === 'error');
        $warnings = array_filter($findings, fn ($f) => $f['severity'] === 'warning');

        match ($this->option('format')) {
            'json' => $this->line((string) json_encode([
                'findings' => array_values($findings),
                'summary' => ['errors' => count($errors), 'warnings' => count($warnings)],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            'junit' => $this->line($this->junit($findings)),
            default => $this->text($findings, count($errors), count($warnings)),
        };

        $failed = count($errors) > 0 || ($this->option('strict') && count($warnings) > 0);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{route:string,uri:string,severity:string,message:string}>  $findings
     */
    private function text(array $findings, int $errors, int $warnings): void
    {
        if ($findings === []) {
            $this->info('✓ fancy-seo: no issues across all linted routes.');

            return;
        }
        foreach ($findings as $f) {
            $where = $f['route'].($f['uri'] !== '' ? " ({$f['uri']})" : '');
            $line = "  {$where}: {$f['message']}";
            $f['severity'] === 'error' ? $this->error($line) : $this->warn($line);
        }
        $this->newLine();
        $this->line("fancy-seo: {$errors} error(s), {$warnings} warning(s).");
    }

    /**
     * @param  list<array{route:string,uri:string,severity:string,message:string}>  $findings
     */
    private function junit(array $findings): string
    {
        $cases = '';
        foreach ($findings as $f) {
            $name = htmlspecialchars($f['route'].' '.$f['message'], ENT_XML1);
            $type = $f['severity'] === 'error' ? 'failure' : 'warning';
            $cases .= '    <testcase name="'.$name.'"><'.$type.' message="'.htmlspecialchars($f['message'], ENT_XML1).'" /></testcase>'."\n";
        }
        $failures = count(array_filter($findings, fn ($f) => $f['severity'] === 'error'));

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<testsuite name="fancy-seo" tests="'.count($findings).'" failures="'.$failures.'">'."\n"
            .$cases
            .'</testsuite>';
    }
}
