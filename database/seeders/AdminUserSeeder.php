<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DbRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * ৩টি Store-এর জন্য ৩টি আলাদা Super Admin Role ও Admin User তৈরি হবে।
     */
    public function run(): void
    {
        $admins = [
            // ── Store 1 : Dhaka ───────────────────────────────────────────────
            [
                'store_id'   => 1,
                'role_id'    => 1,
                'role_name'  => 'Super Admin',
                'name'       => 'Admin Dhaka',
                'first_name' => 'Admin',
                'last_name'  => 'Dhaka',
                'username'   => 'admin_dhaka',
                'email'      => 'admin.dhaka@corevisys.com',
                'password'   => 'password',
            ],
            // ── Store 2 : Chittagong ──────────────────────────────────────────
            [
                'store_id'   => 2,
                'role_id'    => 2,
                'role_name'  => 'Super Admin',
                'name'       => 'Admin Chittagong',
                'first_name' => 'Admin',
                'last_name'  => 'Chittagong',
                'username'   => 'admin_ctg',
                'email'      => 'admin.ctg@corevisys.com',
                'password'   => 'password',
            ],
            // ── Store 3 : Sylhet ──────────────────────────────────────────────
            [
                'store_id'   => 3,
                'role_id'    => 3,
                'role_name'  => 'Super Admin',
                'name'       => 'Admin Sylhet',
                'first_name' => 'Admin',
                'last_name'  => 'Sylhet',
                'username'   => 'admin_sylhet',
                'email'      => 'admin.sylhet@corevisys.com',
                'password'   => 'password',
            ],
        ];

        foreach ($admins as $adminData) {
            // প্রতিটি Store-এর জন্য আলাদা Super Admin Role নিশ্চিত করা
            $role = DbRole::firstOrCreate(
                ['id' => $adminData['role_id']],
                [
                    'role_name'   => $adminData['role_name'],
                    'description' => 'Super Admin Role for Store ' . $adminData['store_id'],
                    'status'      => 1,
                    'store_id'    => $adminData['store_id'],
                    // Explicit global-privilege flag (the role NAME no longer grants
                    // super-admin bypass on its own).
                    'is_super_admin' => true,
                ]
            );

            // Admin User তৈরি — ইতিমধ্যে থাকলে skip করা হবে
            if (!User::where('email', $adminData['email'])->exists()) {
                User::create([
                    'name'         => $adminData['name'],
                    'first_name'   => $adminData['first_name'],
                    'last_name'    => $adminData['last_name'],
                    'username'     => $adminData['username'],
                    'email'        => $adminData['email'],
                    'password'     => Hash::make($adminData['password']),
                    'role_id'      => $role->id,
                    'role_name'    => $role->role_name,
                    'status'       => 1,
                    'store_id'     => $adminData['store_id'],
                    'created_date' => now()->format('Y-m-d'),
                    'created_time' => now()->format('H:i:s'),
                ]);
            }
        }
    }
}
