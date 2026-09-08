<?php

use App\Models\SeoMeta;
use Database\Seeders\SeoMetaSeeder;

test('public homepage renders with complete dynamic SEO metadata and JSON-LD schema', function () {
    $this->seed(SeoMetaSeeder::class);

    $response = $this->get('/');

    $response->assertOk();

    // Check meta tags in rendered HTML
    $response->assertSee('<title>CorevisysPOS — Multi-Warehouse POS &amp; Retail Inventory Software</title>', false);
    $response->assertSee('<meta name="description" content="Fast cloud POS with multi-warehouse inventory, barcode scanning, EMI installment billing, serialized tracking, SMS alerts, and full financial accounting.">', false);
    $response->assertSee('<meta property="og:type" content="website">', false);
    $response->assertSee('<meta property="og:title" content="CorevisysPOS — Complete Retail POS &amp; Multi-Warehouse Inventory System">', false);
    $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('SoftwareApplication', false);
    $response->assertSee('Point of Sale (POS) Checkout & Barcode Scanning', false);
});

test('public privacy policy page renders with legal SEO metadata and schema', function () {
    $this->seed(SeoMetaSeeder::class);

    $response = $this->get('/privacy');

    $response->assertOk();
    $response->assertSee('<title>Privacy Policy &amp; Data Security — CorevisysPOS Intel</title>', false);
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<meta property="og:title" content="Privacy Policy &amp; Merchant Data Protection — CorevisysPOS">', false);
    $response->assertSee('Privacy Policy', false);
    $response->assertSee('Role-Based Access Control', false);
});

test('public terms of service page renders with legal SEO metadata and schema', function () {
    $this->seed(SeoMetaSeeder::class);

    $response = $this->get('/terms');

    $response->assertOk();
    $response->assertSee('<title>Terms of Service &amp; Licensing — CorevisysPOS Intel</title>', false);
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<meta property="og:title" content="Terms of Service &amp; Licensing Agreement — CorevisysPOS">', false);
    $response->assertSee('Terms of Service', false);
});

test('dynamic sitemap endpoint returns valid xml listing only public indexable pages', function () {
    $this->seed(SeoMetaSeeder::class);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

    $content = $response->getContent();

    expect($content)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($content)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->and($content)->toContain(url('/'))
        ->and($content)->toContain(url('/privacy'))
        ->and($content)->toContain(url('/terms'))
        ->and($content)->not->toContain('/dashboard')
        ->and($content)->not->toContain('/sales')
        ->and($content)->not->toContain('/purchase')
        ->and($content)->not->toContain('/reports');
});

test('robots txt disallows private authenticated routes and specifies sitemap location', function () {
    $robotsPath = public_path('robots.txt');
    expect(file_exists($robotsPath))->toBeTrue();

    $content = file_get_contents($robotsPath);

    expect($content)->toContain('Disallow: /dashboard')
        ->and($content)->toContain('Disallow: /sales')
        ->and($content)->toContain('Disallow: /purchase')
        ->and($content)->toContain('Disallow: /reports')
        ->and($content)->toContain('Disallow: /settings')
        ->and($content)->toContain('Allow: /$')
        ->and($content)->toContain('Allow: /privacy$')
        ->and($content)->toContain('Allow: /terms$')
        ->and($content)->toContain('Sitemap: /sitemap.xml');
});

test('seo meta seeder runs idempotently without duplicates', function () {
    $this->seed(SeoMetaSeeder::class);
    $count1 = SeoMeta::count();

    // Re-seed
    $this->seed(SeoMetaSeeder::class);
    $count2 = SeoMeta::count();

    expect($count1)->toBe(5)
        ->and($count2)->toBe(5);
});
