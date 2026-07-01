<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed all roles and permissions for the DGT System.
     *
     * ROLES (in order of authority):
     *   1. super-admin  — Full system access, cannot be restricted
     *   2. admin        — User management, all modules
     *   3. supervisor   — Task approval, team oversight
     *   4. staff        — Create & manage own tasks
     *   5. sales-crm    — Full CRM access, sales pipeline
     *   6. digital-team — Kanban board access (video, graphic, eBay, website)
     *   7. boss         — Read-only reports + dashboard, email notifications
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Define all permissions by module ────────────────────────────
        $permissions = [
            // Dashboard
            'dashboard.view',

            // Users (Admin)
            'users.view', 'users.create', 'users.edit', 'users.delete',

            // Roles & Permissions
            'roles.view', 'roles.manage',

            // Kanban Board
            'kanban.view', 'kanban.create', 'kanban.edit', 'kanban.delete',
            'kanban.assign', 'kanban.approve', 'kanban.reject',

            // CRM
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete',
            'crm.export',

            // eBay authorization (Hongling / Dennis only)
            'authorize-ebay-offers',

            // Logistic management
            'logistic.manage',

            // Sales Pipeline
            'sales.view', 'sales.manage',

            // Reports
            'reports.view', 'reports.export',

            // Settings
            'settings.view', 'settings.manage',

            // Security (IP ban, activity logs)
            'security.view', 'security.manage',

            // Backup
            'backup.run', 'backup.view',

            // Social Media Team
            'social-media.view', 'social-media.submit', 'social-media.qc',
            'social-media.manage', 'social-media.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─── Create roles and assign permissions ──────────────────────────

        // 1. Super Admin — gets all permissions via Gate::before bypass
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. Admin Digital
        $adminDigital = Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        $adminDigital->syncPermissions([
            'dashboard.view',
            'users.view', 'users.create', 'users.edit',
            'roles.view',
            'kanban.view', 'kanban.create', 'kanban.edit', 'kanban.delete', 'kanban.assign', 'kanban.approve', 'kanban.reject',
            'reports.view', 'reports.export',
            'settings.view',
            'security.view',
            'social-media.view', 'social-media.submit', 'social-media.qc', 'social-media.manage', 'social-media.export',
        ]);

        // 3. Admin CRM
        $adminCrm = Role::firstOrCreate(['name' => 'admin-crm', 'guard_name' => 'web']);
        $adminCrm->syncPermissions([
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.manage',
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.export',
            'authorize-ebay-offers',
            'logistic.manage',
            'sales.view', 'sales.manage',
            'reports.view', 'reports.export',
            'security.view', 'security.manage',
            'backup.run', 'backup.view',
        ]);



        // 5. Sales/CRM
        $salesCrm = Role::firstOrCreate(['name' => 'sales-crm', 'guard_name' => 'web']);
        $salesCrm->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete',
            'sales.view', 'sales.manage',
            'reports.view',
        ]);

        // 6. Digital Team
        $digitalTeam = Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);
        $digitalTeam->syncPermissions([
            'dashboard.view',
            'kanban.view', 'kanban.create', 'kanban.edit',
            'social-media.view', 'social-media.submit',
        ]);

        // 7. Boss — read-only oversight
        $boss = Role::firstOrCreate(['name' => 'boss', 'guard_name' => 'web']);
        $boss->syncPermissions([
            'dashboard.view',
            'reports.view',
            'kanban.view', 'kanban.approve',
            'crm.view',
            'sales.view',
            'authorize-ebay-offers', // Boss can authorize eBay offers
            'social-media.view',
            'social-media.export',
        ]);

        // 8. Social Admin
        $socialAdmin = Role::firstOrCreate(['name' => 'social_admin', 'guard_name' => 'web']);
        $socialAdmin->syncPermissions([
            'dashboard.view',
            'social-media.view', 'social-media.submit', 'social-media.qc', 'social-media.export',
        ]);

        // 9. Social QC
        $socialQc = Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        $socialQc->syncPermissions([
            'dashboard.view',
            'social-media.view', 'social-media.qc', 'social-media.manage', 'social-media.export',
        ]);

        $this->command->info('✅ Roles and permissions seeded successfully.');
        $this->command->table(
            ['Role', 'Permission Count'],
            Role::all()->map(fn ($r) => [$r->name, $r->permissions->count()])
        );
    }
}
