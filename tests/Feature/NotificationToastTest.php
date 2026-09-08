<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationToastTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $store = DbStore::firstOrCreate(['id' => 1], [
            'store_code' => 'ST001',
            'store_name' => 'Main Branch',
            'status' => 1,
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => ['reports_view', 'sales_report'],
        ]);

        $this->user = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);
    }

    public function test_notification_toast_component_is_rendered_in_app_layout()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('reports.sales_summary'));

        $response->assertStatus(200);
        $response->assertSee('Alpine.store(\'notify\'', false);
        $response->assertSee('window.showSuccess', false);
        $response->assertSee('window.showError', false);
    }

    public function test_success_flash_message_triggers_toast_notification()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1, 'success' => 'Item Created Successfully!'])
            ->get(route('reports.sales_summary'));

        $response->assertStatus(200);
        $response->assertSee('Item Created Successfully!');
        $response->assertSee('showSuccess');
    }

    public function test_error_flash_message_triggers_toast_notification()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1, 'error' => 'Database connection failed!'])
            ->get(route('reports.sales_summary'));

        $response->assertStatus(200);
        $response->assertSee('Database connection failed!');
        $response->assertSee('showError');
    }

    public function test_validation_errors_trigger_toast_notification()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['item_name' => ['The item name field is required.']]))])
            ->get(route('reports.sales_summary'));

        $response->assertStatus(200);
        $response->assertSee('The item name field is required.');
        $response->assertSee('showError');
    }
}
