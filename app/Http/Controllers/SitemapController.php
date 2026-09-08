<?php

namespace App\Http\Controllers;

use App\Models\SeoMeta;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic XML sitemap for public indexable pages.
     */
    public function index(): Response
    {
        $publicPages = SeoMeta::where('is_indexable', true)->get();

        // Fallback default list if no database records exist
        if ($publicPages->isEmpty()) {
            $urls = [
                [
                    'loc' => url('/'),
                    'lastmod' => now()->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '1.0',
                ],
                [
                    'loc' => url('/privacy'),
                    'lastmod' => now()->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ],
                [
                    'loc' => url('/terms'),
                    'lastmod' => now()->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ],
            ];
        } else {
            $urls = $publicPages->map(function ($page) {
                $loc = match ($page->page_key) {
                    'home' => url('/'),
                    'privacy' => url('/privacy'),
                    'terms' => url('/terms'),
                    default => $page->canonical_url ?: url('/' . ltrim($page->page_key, '/')),
                };

                return [
                    'loc' => $loc,
                    'lastmod' => ($page->updated_at ?: now())->toAtomString(),
                    'changefreq' => $page->changefreq ?: 'weekly',
                    'priority' => number_format((float) ($page->priority ?: 0.8), 1),
                ];
            })->toArray();
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
            $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
            $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex', // Prevent the sitemap XML file itself from being indexed as a document
        ]);
    }
}
