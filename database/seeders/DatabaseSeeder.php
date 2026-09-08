<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call(CurrencySeeder::class);
        $this->call(LanguageSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(StoreSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(SmsTemplateSeeder::class);
        $this->call(SmsAutoRuleSeeder::class);
        $this->call(StateSeeder::class);
        $this->call(PaymentTypeSeeder::class);
        $this->call(UnitSeeder::class);
        $this->call(TaxSeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(EndToEndCouponSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(SiteSettingsSeeder::class);
        $this->call(SeoMetaSeeder::class);
    }
}
