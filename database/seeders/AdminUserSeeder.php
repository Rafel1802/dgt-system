<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the default Super Admin user.
     *
     * IMPORTANT: Change the password after first login!
     * Password policy: min 12 chars, mixed case, numbers, symbols.
     */
    public function run(): void
    {
        // Ensure the super-admin role exists
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@dgt.local'],
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@dgt.local',
                'password' => Hash::make('SuperAdmin@123!'),  // CHANGE THIS IMMEDIATELY
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->syncRoles([$superAdminRole]);

        // Create Admin user
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@dgt.local'],
            [
                'name' => 'System Admin',
                'email' => 'admin@dgt.local',
                'password' => Hash::make('Admin@123456!'),    // CHANGE THIS IMMEDIATELY
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([$adminRole]);

        $this->command->info('✅ Default users seeded:');
        $this->command->table(
            ['Name', 'Email', 'Role', 'Default Password'],
            [
                ['Super Administrator', 'superadmin@dgt.local', 'super-admin', 'SuperAdmin@123!'],
                ['System Admin', 'admin@dgt.local', 'admin', 'Admin@123456!'],
            ]
        );

        $this->command->warn('⚠️  IMPORTANT: Change all default passwords immediately after first login!');
    }
}
