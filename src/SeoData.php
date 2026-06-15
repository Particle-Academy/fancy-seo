<?php

namespace FancySeo;

/**
 * The resolved SEO payload for one request. Immutable; produced by
 * {@see FancySeo::forRequest()} and consumed by the `<x-fancy-seo::head>`
 * Blade component (or your own template via {@see toArray()}).
 */
class SeoData
{
    /**
     * @param  list<array<string,mixed>>  $jsonLd
     * @param  list<string>  $keywords
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly ?string $image,
        public readonly string $type,
        public readonly string $siteName,
        public readonly string $locale,
        public readonly string $robots,
        public readonly array $jsonLd = [],
        public readonly array $keywords = [],
        public readonly ?string $twitterSite = null,
        public readonly ?string $themeColor = null,
        public readonly string $twitterCard = 'summary_large_image',
        public readonly ?string $imageAlt = null,
        public readonly ?int $imageWidth = null,
        public readonly ?int $imageHeight = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'image' => $this->image,
            'type' => $this->type,
            'siteName' => $this->siteName,
            'locale' => $this->locale,
            'robots' => $this->robots,
            'jsonLd' => $this->jsonLd,
            'keywords' => $this->keywords,
            'twitterSite' => $this->twitterSite,
            'themeColor' => $this->themeColor,
            'twitterCard' => $this->twitterCard,
            'imageAlt' => $this->imageAlt,
            'imageWidth' => $this->imageWidth,
            'imageHeight' => $this->imageHeight,
        ];
    }
}
