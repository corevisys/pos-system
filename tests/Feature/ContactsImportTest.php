<?php

namespace Tests\Feature;

use App\Models\DbCustomer;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactsImportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Import Test Store',
            'status' => 1,
            'mobile' => '+8801700000000',
            'customer_init' => 'CU',
            'supplier_init' => 'SUP',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 1], [
            'store_id' => 1,
            'permissions' => [
                'customers_view', 
                'customers_add', 
                'customers_import_customers',
                'suppliers_view', 
                'suppliers_add', 
                'suppliers_import_suppliers'
            ],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);
    }

    public function test_customer_import_page_renders_and_template_downloads()
    {
        $response = $this->actingAs($this->user)->get(route('contacts.customers.import'));
        $response->assertStatus(200);
        $response->assertSee('Import Customers');
        $response->assertSee('Upload CSV File');
        $response->assertSee('CSV Columns Specification');

        $templateResponse = $this->actingAs($this->user)->get(route('contacts.customers.import.template'));
        $templateResponse->assertStatus(200);
        $templateResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $templateResponse->streamedContent();
        $this->assertStringContainsString('Customer Name', $content);
        $this->assertStringContainsString('Previous Due', $content);
        $this->assertStringContainsString('Credit Limit', $content);
        $this->assertStringContainsString('John Doe', $content);
    }

    public function test_supplier_import_page_renders_and_template_downloads()
    {
        $response = $this->actingAs($this->user)->get(route('contacts.suppliers.import'));
        $response->assertStatus(200);
        $response->assertSee('Import Suppliers');
        $response->assertSee('Upload CSV File');
        $response->assertSee('CSV Columns Specification');

        $templateResponse = $this->actingAs($this->user)->get(route('contacts.suppliers.import.template'));
        $templateResponse->assertStatus(200);
        $templateResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $templateResponse->streamedContent();
        $this->assertStringContainsString('Supplier Name', $content);
        $this->assertStringContainsString('Opening Balance', $content);
        $this->assertStringContainsString('Apex Suppliers Ltd', $content);
    }

    public function test_customer_import_success_with_auto_generated_code_and_balance()
    {
        $csvContent = "Customer Name,Mobile,Email,Phone,GST Number,TAX Number,Previous Due,Credit Limit,Country Name,State Name,Postcode,Address,Location Link\n";
        $csvContent .= "Alice Walker,01700112233,alice@example.com,01700112233,GST111,TAX111,250.50,10000.00,Bangladesh,Dhaka,1200,Road 1,https://maps.google.com\n";
        $csvContent .= "Bob Vance,01700445566,bob@example.com,,,,,0.00,-1,Bangladesh,Dhaka,1200,Road 2,\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('contacts.customers.import.store'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('contacts.customers.import'));
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 2 && $summary['skipped'] === 0;
        });

        $this->assertDatabaseHas('db_customers', [
            'customer_name' => 'Alice Walker',
            'mobile' => '01700112233',
            'email' => 'alice@example.com',
            'opening_balance' => 250.50,
            'credit_limit' => 10000.00,
            'delete_bit' => 0,
        ]);

        $this->assertDatabaseHas('db_customers', [
            'customer_name' => 'Bob Vance',
            'mobile' => '01700445566',
            'email' => 'bob@example.com',
            'delete_bit' => 0,
        ]);

        // Generated customer codes must be sequential and distinct. Assert the RELATIVE
        // sequence rather than absolute CU-000001/CU-000002: on a shared MySQL test DB
        // the auto-increment for db_customers is non-transactional and drifts upward
        // across RefreshDatabase rollbacks, so the first imported customer of this test
        // is not necessarily id 1.
        $codes = \App\Models\DbCustomer::whereIn('mobile', ['01700112233', '01700445566'])
            ->orderBy('id')
            ->pluck('customer_code')
            ->all();
        $this->assertCount(2, $codes);
        // The first customer of an empty store always gets CU-000001 (the generator
        // sequences from max(id) = 0), so this part is engine-independent.
        $this->assertSame('CU-000001', $codes[0]);
        $this->assertMatchesRegularExpression('/^CU-\d{6}$/', $codes[1]);
        $this->assertNotSame($codes[0], $codes[1]);
    }

    public function test_customer_import_skips_duplicates_and_missing_required_fields()
    {
        DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Existing Customer',
            'customer_code' => 'CU-000001',
            'mobile' => '01711223344',
            'email' => 'existing@example.com',
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $csvContent = "Customer Name,Mobile,Email,Phone,GST Number,TAX Number,Previous Due,Credit Limit\n";
        // Row 2: Duplicate mobile
        $csvContent .= "Dup Mobile,01711223344,dup1@example.com,,,,,\n";
        // Row 3: Duplicate email
        $csvContent .= "Dup Email,01799887766,existing@example.com,,,,,\n";
        // Row 4: Missing customer name
        $csvContent .= ",01755443322,noname@example.com,,,,,\n";
        // Row 5: Valid customer (Previous Due = 500.00, Credit Limit = 1000.00)
        $csvContent .= "Valid Customer,01788776655,valid@example.com,,,,500.00,1000.00\n";

        $file = UploadedFile::fake()->createWithContent('customers_mixed.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('contacts.customers.import.store'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('contacts.customers.import'));
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 1 && $summary['skipped'] === 3 && count($summary['errors']) === 3;
        });

        $this->assertDatabaseHas('db_customers', [
            'customer_name' => 'Valid Customer',
            'mobile' => '01788776655',
            'email' => 'valid@example.com',
            'opening_balance' => 500.00,
        ]);

        $this->assertDatabaseMissing('db_customers', [
            'customer_name' => 'Dup Mobile',
        ]);
        $this->assertDatabaseMissing('db_customers', [
            'customer_name' => 'Dup Email',
        ]);
    }

    public function test_supplier_import_success_with_auto_generated_code()
    {
        $csvContent = "Supplier Name,Mobile,Email,Phone,GST Number,TAX Number,Country Name,State Name,Postcode,Address,Opening Balance\n";
        $csvContent .= "Prime Suppliers Ltd,01811223344,prime@example.com,01811223344,GST222,TAX222,Bangladesh,Dhaka,1200,Industrial Area,1200.00\n";
        $csvContent .= "Nova Traders,01811556677,nova@example.com,,,,,,,,0.00\n";

        $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('contacts.suppliers.import.store'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('contacts.suppliers.import'));
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 2 && $summary['skipped'] === 0;
        });

        $this->assertDatabaseHas('db_suppliers', [
            'supplier_name' => 'Prime Suppliers Ltd',
            'mobile' => '01811223344',
            'email' => 'prime@example.com',
            'opening_balance' => 1200.00,
            'delete_bit' => 0,
        ]);

        $this->assertDatabaseHas('db_suppliers', [
            'supplier_name' => 'Nova Traders',
            'mobile' => '01811556677',
            'email' => 'nova@example.com',
            'delete_bit' => 0,
        ]);

        // Generated supplier codes must be sequential and distinct. Assert the RELATIVE
        // sequence rather than absolute SUP-000001/SUP-000002: on a shared MySQL test DB
        // the auto-increment for db_suppliers is non-transactional and drifts upward
        // across RefreshDatabase rollbacks, so the first imported supplier is not
        // necessarily id 1.
        $codes = \App\Models\DbSupplier::whereIn('mobile', ['01811223344', '01811556677'])
            ->orderBy('id')
            ->pluck('supplier_code')
            ->all();
        $this->assertCount(2, $codes);
        $this->assertSame('SUP-000001', $codes[0]);
        $this->assertMatchesRegularExpression('/^SUP-\d{6}$/', $codes[1]);
        $this->assertNotSame($codes[0], $codes[1]);
    }

    public function test_supplier_import_handles_duplicate_mobile_and_email_gracefully()
    {
        // Seeded in the importing user's store (store 1) so the per-store duplicate
        // checks inside importStore() treat it as an existing same-store supplier.
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Existing Supplier',
            'supplier_code' => 'SUP-000001',
            'mobile' => '01811998877',
            'email' => 'existing_sup@example.com',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $csvContent = "Supplier Name,Mobile,Email,Phone,GST Number,TAX Number,Country Name,State Name,Postcode,Address,Opening Balance\n";
        // Row 2: Duplicate mobile
        $csvContent .= "Dup Mobile Sup,01811998877,dup_sup1@example.com,,,,,,,,0.00\n";
        // Row 3: Duplicate email
        $csvContent .= "Dup Email Sup,01855443322,existing_sup@example.com,,,,,,,,0.00\n";
        // Row 4: Missing supplier name
        $csvContent .= ",01866554433,noname_sup@example.com,,,,,,,,0.00\n";
        // Row 5: Valid supplier
        $csvContent .= "Valid Supplier,01877665544,valid_sup@example.com,,,,,,,,750.00\n";

        $file = UploadedFile::fake()->createWithContent('suppliers_mixed.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('contacts.suppliers.import.store'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('contacts.suppliers.import'));
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 1 && $summary['skipped'] === 3 && count($summary['errors']) === 3;
        });

        $this->assertDatabaseHas('db_suppliers', [
            'supplier_name' => 'Valid Supplier',
            'mobile' => '01877665544',
            'email' => 'valid_sup@example.com',
            'opening_balance' => 750.00,
        ]);

        $this->assertDatabaseMissing('db_suppliers', [
            'supplier_name' => 'Dup Mobile Sup',
        ]);
        $this->assertDatabaseMissing('db_suppliers', [
            'supplier_name' => 'Dup Email Sup',
        ]);
    }

    public function test_import_validation_requires_csv_file()
    {
        $responseCust = $this->actingAs($this->user)->post(route('contacts.customers.import.store'), []);
        $responseCust->assertSessionHasErrors('import_file');

        $responseSup = $this->actingAs($this->user)->post(route('contacts.suppliers.import.store'), []);
        $responseSup->assertSessionHasErrors('import_file');
    }
}
