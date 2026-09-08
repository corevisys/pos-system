<?php

namespace Database\Seeders;

use App\Models\SeoMeta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SeoMetaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'page_key' => 'home',
                'title' => 'CorevisysPOS — Multi-Warehouse POS & Retail Inventory Software',
                'meta_description' => 'Fast cloud POS with multi-warehouse inventory, barcode scanning, EMI installment billing, serialized tracking, SMS alerts, and full financial accounting.',
                'meta_keywords' => 'point of sale software, multi warehouse inventory, retail pos bangladesh, pos billing system, emi installment pos, barcode scanning pos, serialized inventory tracking, retail store accounting',
                'og_title' => 'CorevisysPOS — Complete Retail POS & Multi-Warehouse Inventory System',
                'og_description' => 'Streamline retail and wholesale billing with fast POS checkout, serialized stock tracking, EMI installment schedules, SMS customer alerts, and real-time profit reporting.',
                'og_image' => '/uploads/seo/corevisys-pos-og-banner.png',
                'twitter_card' => 'summary_large_image',
                'canonical_url' => url('/'),
                'schema_type' => 'SoftwareApplication',
                'is_indexable' => true,
                'changefreq' => 'daily',
                'priority' => 1.0,
            ],
            [
                'page_key' => 'privacy',
                'title' => 'Privacy Policy & Data Security — CorevisysPOS Intel',
                'meta_description' => 'Learn how CorevisysPOS protects merchant data, customer records, and transaction ledgers with strict role-based access control and encrypted backups.',
                'meta_keywords' => 'corevisys pos privacy policy, pos data protection, merchant security terms, retail data confidentiality, rbac pos security',
                'og_title' => 'Privacy Policy & Merchant Data Protection — CorevisysPOS',
                'og_description' => 'Our commitment to data privacy, secure cloud database backups, role-based access restrictions, and merchant transaction confidentiality.',
                'og_image' => '/uploads/seo/corevisys-pos-og-banner.png',
                'twitter_card' => 'summary_large_image',
                'canonical_url' => url('/privacy'),
                'schema_type' => 'WebPage',
                'is_indexable' => true,
                'changefreq' => 'monthly',
                'priority' => 0.5,
            ],
            [
                'page_key' => 'terms',
                'title' => 'Terms of Service & Licensing — CorevisysPOS Intel',
                'meta_description' => 'Read the terms of service and software licensing agreement governing the use of CorevisysPOS point of sale and inventory management software.',
                'meta_keywords' => 'corevisys pos terms of service, pos software license, merchant agreement, point of sale terms, cloud pos service terms',
                'og_title' => 'Terms of Service & Licensing Agreement — CorevisysPOS',
                'og_description' => 'Review the official licensing terms, operational service agreements, and software usage policies for CorevisysPOS enterprise retail suite.',
                'og_image' => '/uploads/seo/corevisys-pos-og-banner.png',
                'twitter_card' => 'summary_large_image',
                'canonical_url' => url('/terms'),
                'schema_type' => 'WebPage',
                'is_indexable' => true,
                'changefreq' => 'monthly',
                'priority' => 0.5,
            ],
            [
                'page_key' => 'login',
                'title' => 'Merchant Login — CorevisysPOS Terminal & Management',
                'meta_description' => 'Log in to your CorevisysPOS terminal and back-office dashboard to manage real-time sales, inventory stock, customer dues, and store analytics.',
                'meta_keywords' => 'corevisys pos login, merchant pos login, pos terminal sign in, retail store portal login',
                'og_title' => 'Merchant Portal Login — CorevisysPOS',
                'og_description' => 'Access your retail terminal, multi-warehouse stock controls, and store accounting dashboard securely.',
                'og_image' => '/uploads/seo/corevisys-pos-og-banner.png',
                'twitter_card' => 'summary_large_image',
                'canonical_url' => url('/login'),
                'schema_type' => 'WebPage',
                'is_indexable' => false,
                'changefreq' => 'monthly',
                'priority' => 0.3,
            ],
            [
                'page_key' => 'register',
                'title' => 'Merchant Registration & Free Trial — CorevisysPOS',
                'meta_description' => 'Sign up for CorevisysPOS and start your 14-day free trial. Experience fast POS billing, multi-warehouse stock control, and automated SMS marketing.',
                'meta_keywords' => 'corevisys pos register, sign up pos trial, retail pos free trial, start pos store account',
                'og_title' => 'Start Your 14-Day Free POS Trial — CorevisysPOS',
                'og_description' => 'Register your business today. Get instant access to multi-warehouse inventory, EMI installment billing, and financial analytics.',
                'og_image' => '/uploads/seo/corevisys-pos-og-banner.png',
                'twitter_card' => 'summary_large_image',
                'canonical_url' => url('/register'),
                'schema_type' => 'WebPage',
                'is_indexable' => false,
                'changefreq' => 'monthly',
                'priority' => 0.4,
            ],
        ];

        foreach ($pages as $page) {
            SeoMeta::updateOrCreate(
                ['page_key' => $page['page_key']],
                $page
            );

            Cache::forget("seo_meta_{$page['page_key']}");
        }
    }
}
