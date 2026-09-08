<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoMeta extends Model
{
    use HasFactory;

    protected $table = 'seo_meta';

    protected $fillable = [
        'page_key',
        'title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'canonical_url',
        'schema_type',
        'schema_json',
        'is_indexable',
        'changefreq',
        'priority',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'is_indexable' => 'boolean',
        'priority' => 'float',
    ];

    /**
     * Retrieve and cache SEO metadata by page_key for 1 hour.
     */
    public static function getMetaFor(string $pageKey): ?self
    {
        return Cache::remember("seo_meta_{$pageKey}", 3600, function () use ($pageKey) {
            return self::where('page_key', $pageKey)->first();
        });
    }

    /**
     * Generate structured JSON-LD schema for this page.
     */
    public function getStructuredData(): array
    {
        if (!empty($this->schema_json)) {
            return $this->schema_json;
        }

        $baseUrl = url('/');

        if ($this->page_key === 'home') {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'CorevisysPOS',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web Browser, Cloud, Windows, Android, iOS',
                'description' => $this->meta_description,
                'url' => $baseUrl,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'BDT',
                    'description' => 'Free 14-day trial with full feature access',
                ],
                'aggregateRating' => [
                    '@type' => 'AggregateRating',
                    'ratingValue' => '4.9',
                    'reviewCount' => '520',
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'featureList' => [
                    'Point of Sale (POS) Checkout & Barcode Scanning',
                    'Multi-Warehouse Inventory Control & Stock Transfers',
                    'Serialized Product Tracking for Electronics & Gadgets',
                    'Installment / EMI Billing & Schedule Management',
                    'Automated SMS Marketing Campaigns & Payment Alerts',
                    'GST / VAT Compliant Tax Invoicing',
                    '22+ Executive Financial & Accounting Reports',
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $this->title,
            'description' => $this->meta_description,
            'url' => $this->canonical_url ?: url()->current(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'CorevisysPOS',
                'url' => $baseUrl,
            ],
        ];
    }
}
