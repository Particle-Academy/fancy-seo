@props(['seo' => null])
@php
    /** @var \FancySeo\SeoData $data */
    $data = $seo ?? app(\FancySeo\FancySeo::class)->forRequest(request());
    $kw = is_array($data->keywords) ? implode(', ', $data->keywords) : (string) $data->keywords;
@endphp
<title inertia>{{ $data->title }}</title>
@if ($data->description !== '')
<meta head-key="description" name="description" content="{{ $data->description }}">
@endif
@if ($kw !== '')
<meta head-key="keywords" name="keywords" content="{{ $kw }}">
@endif
<link head-key="canonical" rel="canonical" href="{{ $data->canonical }}">
<meta head-key="robots" name="robots" content="{{ $data->robots }}">
@if ($data->themeColor)
<meta name="theme-color" content="{{ $data->themeColor }}">
@endif

{{-- Open Graph --}}
<meta head-key="og:type" property="og:type" content="{{ $data->type }}">
<meta head-key="og:site_name" property="og:site_name" content="{{ $data->siteName }}">
<meta head-key="og:title" property="og:title" content="{{ $data->title }}">
@if ($data->description !== '')
<meta head-key="og:description" property="og:description" content="{{ $data->description }}">
@endif
<meta head-key="og:url" property="og:url" content="{{ $data->canonical }}">
@if ($data->image)
<meta head-key="og:image" property="og:image" content="{{ $data->image }}">
@endif
<meta head-key="og:locale" property="og:locale" content="{{ $data->locale }}">

{{-- Twitter --}}
<meta head-key="twitter:card" name="twitter:card" content="{{ $data->twitterCard }}">
@if ($data->twitterSite)
<meta head-key="twitter:site" name="twitter:site" content="{{ $data->twitterSite }}">
@endif
<meta head-key="twitter:title" name="twitter:title" content="{{ $data->title }}">
@if ($data->description !== '')
<meta head-key="twitter:description" name="twitter:description" content="{{ $data->description }}">
@endif
@if ($data->image)
<meta head-key="twitter:image" name="twitter:image" content="{{ $data->image }}">
@endif

{{-- LLM / AI discovery --}}
@if (config('fancy-seo.routes.llms', true))
<link rel="alternate" type="text/markdown" title="llms.txt" href="{{ app(\FancySeo\FancySeo::class)->baseUrl() }}/llms.txt">
@endif

{{-- Structured data --}}
@foreach ($data->jsonLd as $node)
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endforeach
