@props([
    'pageKey' => 'home',
    'meta' => null,
    'title' => null,
    'description' => null,
    'keywords' => null,
    'image' => null,
    'canonical' => null,
])

@php
    $seo = $meta ?? \App\Models\SeoMeta::getMetaFor($pageKey);

    $pageTitle = $title ?? ($seo?->title ?? config('app.name', 'CorevisysPOS') . ' — Retail Point of Sale & Inventory');
    $metaDescription = $description ?? ($seo?->meta_description ?? 'Enterprise Point of Sale (POS) and Multi-Warehouse Inventory Management Software.');
    $metaKeywords = $keywords ?? ($seo?->meta_keywords ?? 'pos, retail pos, inventory management, multi-warehouse, bangladesh pos');
    $canonicalUrl = $canonical ?? ($seo?->canonical_url ?: url()->current());
    $ogTitle = $seo?->og_title ?: $pageTitle;
    $ogDescription = $seo?->og_description ?: $metaDescription;
    $ogImage = $image ?? ($seo?->og_image ? url($seo->og_image) : url('/favicon.ico'));
    $twitterCard = $seo?->twitter_card ?: 'summary_large_image';
    $isIndexable = $seo ? $seo->is_indexable : true;
    $robotsDirectives = $isIndexable ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, nofollow';

    $schemaData = $seo ? $seo->getStructuredData() : [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => config('app.name', 'CorevisysPOS'),
        'applicationCategory' => 'BusinessApplication',
        'description' => $metaDescription,
        'url' => url('/'),
    ];
@endphp

<!-- Primary Meta Tags -->
<title>{{ $pageTitle }}</title>
<meta name="title" content="{{ $pageTitle }}">
<meta name="description" content="{{ $metaDescription }}">
@if(!empty($metaKeywords))
<meta name="keywords" content="{{ $metaKeywords }}">
@endif
<meta name="robots" content="{{ $robotsDirectives }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="CorevisysPOS">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:image" content="{{ $ogImage }}">

<!-- Twitter Card -->
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:url" content="{{ $canonicalUrl }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<!-- Structured Data (Schema.org JSON-LD) -->
<script type="application/ld+json">
{!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
