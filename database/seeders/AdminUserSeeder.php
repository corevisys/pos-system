<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure Super Admin role exists
        $role = \App\Models\DbRole::firstOrCreate(
            ['id' => 1],
            [
                'role_name' => 'Super Admin',
                'description' => 'Super Admin Role',
                'status' => 1,
                'store_id' => 1,
            ]
        );

        // Check if admin user exists to avoid duplicates
        $adminEmail = 'admin@example.com';
        
        if (!User::where('email', $adminEmail)->exists()) {
            User::create([
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => 'admin',
                'email' => $adminEmail,
                'password' => Hash::make('password'),
                'role_id' => $role->id,
                'role_name' => $role->role_name,
                'status' => 1,
                'store_id' => 1,
                'created_date' => now()->format('Y-m-d'),
                'created_time' => now()->format('H:i:s'),
            ]);
        }
    }
}
