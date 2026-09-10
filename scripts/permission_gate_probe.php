<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "PERMISSION_GATE_PROBE\n";

// Create a role with id 50 (non-super-admin) and no store_settings permission
$role = \App\Models\DbRole::create([
    'id' => 50,
    'role_name' => 'No Perm Probe',
    'status' => 1,
    'store_id' => 1,
]);
echo "role id=" . $role->id . "\n";

$perm = \App\Models\DbPermission::create([
    'role_id' => $role->id,
    'permissions' => ['sales_view'],
]);
echo "perm role_id=" . $perm->role_id . " permissions=" . json_encode($perm->permissions) . "\n";

$user = \App\Models\User::create([
    'name' => 'Probe User',
    'email' => 'probe_' . uniqid() . '@example.com',
    'password' => bcrypt('password'),
    'role_id' => $role->id,
    'role_name' => $role->role_name,
    'store_id' => 1,
    'status' => 1,
]);
echo "user id=" . $user->id . " role_id=" . $user->role_id . " role_name=" . $user->role_name . "\n";
echo "isSuperAdmin=" . var_export($user->isSuperAdmin(), true) . "\n";
echo "role loaded=" . ($user->role ? 'yes' : 'no') . "\n";
echo "role permissions=" . ($user->role && $user->role->permissions ? json_encode($user->role->permissions->permissions) : 'none') . "\n";
echo "hasPermission('store_settings_view')=" . var_export($user->hasPermission('store_settings_view'), true) . "\n";
echo "hasPermission('store_settings_edit')=" . var_export($user->hasPermission('store_settings_edit'), true) . "\n";
echo "hasPermission('sales_view')=" . var_export($user->hasPermission('sales_view'), true) . "\n";
