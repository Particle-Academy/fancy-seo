<?php

namespace FancySeo;

/**
 * Dependency-free schema.org JSON-LD builders — the PHP mirror of
 * `@particle-academy/fancy-inertia/seo`'s builders. Each returns a plain array
 * you hand to `FancySeo::for(['jsonLd' => [...]])` or render directly.
 */
class JsonLd
{
    private const CONTEXT = 'https://schema.org';

    /** @return array<string,mixed> */
    public static function website(string $name, string $url, ?string $description = null, ?string $searchUrlTemplate = null): array
    {
        $node = ['@context' => self::CONTEXT, '@type' => 'WebSite', 'name' => $name, 'url' => $url];
        if ($description !== null) {
            $node['description'] = $description;
        }
        if ($searchUrlTemplate !== null) {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $searchUrlTemplate],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }

    /**
     * @param  list<string>  $sameAs
     * @return array<string,mixed>
     */
    public static function organization(string $name, string $url, ?string $logo = null, array $sameAs = []): array
    {
        $node = ['@context' => self::CONTEXT, '@type' => 'Organization', 'name' => $name, 'url' => $url];
        if ($logo !== null) {
            $node['logo'] = $logo;
        }
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * @param  array<string,mixed>  $opts  description, applicationCategory, operatingSystem, softwareVersion, price, priceCurrency
     * @return array<string,mixed>
     */
    public static function softwareApplication(string $name, string $url, array $opts = []): array
    {
        $node = ['@context' => self::CONTEXT, '@type' => 'SoftwareApplication', 'name' => $name, 'url' => $url];
        foreach (['description', 'applicationCategory', 'operatingSystem', 'softwareVersion'] as $key) {
            if (isset($opts[$key])) {
                $node[$key] = $opts[$key];
            }
        }
        if (isset($opts['price'])) {
            $node['offers'] = ['@type' => 'Offer', 'price' => (string) $opts['price'], 'priceCurrency' => $opts['priceCurrency'] ?? 'USD'];
        }

        return $node;
    }

    /**
     * @param  array<string,mixed>  $opts  programmingLanguage, description, license
     * @return array<string,mixed>
     */
    public static function softwareSourceCode(string $name, string $url, string $codeRepository, array $opts = []): array
    {
        $node = [
            '@context' => self::CONTEXT,
            '@type' => 'SoftwareSourceCode',
            'name' => $name,
            'url' => $url,
            'codeRepository' => $codeRepository,
        ];
        foreach (['programmingLanguage', 'description', 'license'] as $key) {
            if (isset($opts[$key])) {
                $node[$key] = $opts[$key];
            }
        }

        return $node;
    }

    /**
     * @param  array<string,mixed>  $opts  description, image, datePublished, dateModified, authorName
     * @return array<string,mixed>
     */
    public static function article(string $headline, string $url, array $opts = []): array
    {
        $node = ['@context' => self::CONTEXT, '@type' => 'Article', 'headline' => $headline, 'url' => $url];
        foreach (['description', 'image', 'datePublished', 'dateModified'] as $key) {
            if (isset($opts[$key])) {
                $node[$key] = $opts[$key];
            }
        }
        if (isset($opts['authorName'])) {
            $node['author'] = ['@type' => 'Person', 'name' => $opts['authorName']];
        }

        return $node;
    }

    /**
     * @param  list<array{name:string,url:string}>  $items
     * @return array<string,mixed>
     */
    public static function breadcrumbList(array $items): array
    {
        return [
            '@context' => self::CONTEXT,
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @param  list<array{question:string,answer:string}>  $items
     * @return array<string,mixed>
     */
    public static function faqPage(array $items): array
    {
        return [
            '@context' => self::CONTEXT,
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ],
                $items,
            ),
        ];
    }

    /** @return array<string,mixed> */
    public static function collectionPage(string $name, string $url, ?string $description = null): array
    {
        $node = ['@context' => self::CONTEXT, '@type' => 'CollectionPage', 'name' => $name, 'url' => $url];
        if ($description !== null) {
            $node['description'] = $description;
        }

        return $node;
    }
}
