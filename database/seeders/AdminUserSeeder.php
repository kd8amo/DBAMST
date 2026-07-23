<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create a default admin user for initial system setup.
     * Password should be changed immediately after first login.
     *
     * This seeder is idempotent — it will not create a duplicate
     * if the admin user already exists.
     */
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if (! $adminRole) {
            $this->command->error('Admin role not found — run RoleSeeder first.');
            return;
        }

        // Check if admin user already exists.
        $existing = DB::table('users')->where('email', 'admin@tsm.local')->first();

        if ($existing) {
            $this->command->info('Admin user already exists — skipping.');
            return;
        }

        $now = now();

        $userId = DB::table('users')->insertGetId([
            'name'          => 'admin',
            'display_name'  => 'System Admin',
            'email'         => 'admin@tsm.local',
            'password_hash' => Hash::make('Admin123!'),
            'role_id'       => $adminRole->id,
            'locale'        => 'en',
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Sync into user_roles pivot so canActAs() works correctly.
        DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $adminRole->id,
        ]);

        $this->command->info("Admin user created: admin@tsm.local / Admin123!");
        $this->command->warn("⚠️  Change the admin password immediately after first login!");
    }
}
