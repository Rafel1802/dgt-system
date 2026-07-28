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
     *   5. sales-crm    — Normal Staff tier: CRM status changes + notes on own
     *                     assigned customers only. Full edit access instead if
     *                     crm_role='supervisor' (User::isCrmSupervisor()).
     *   6. digital-team — Kanban board access (video, graphic, eBay, website)
     *   7. boss         — Read-only reports + dashboard, email notifications
     *   8. ebay-supervisor     — Can delete eBay records (offers, customer records, stores)
     *   9. logistic-supervisor — Can delete Logistics records (shipments, trucking companies)
     *  10. ebay-team           — Normal Staff tier, same as sales-crm above
     *  11. logistic-team       — Normal Staff tier, same as sales-crm above
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
            // Normal Staff tier (ebay-team, logistic-team, plain sales-crm):
            // status changes + notes only — see CustomerPolicy/CustomerController
            // field whitelist. Does NOT grant crm.edit's full-record access.
            'crm.status-update',

            // eBay authorization (Hongling / Dennis only)
            'authorize-ebay-offers',

            // Logistic management
            'logistic.manage',

            // Sales Pipeline
            'sales.view', 'sales.manage',

            // Technical Support cases
            'tech-support.manage',

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
            'dashboard.view',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.manage',
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.export',
            'authorize-ebay-offers',
            'logistic.manage',
            'sales.view', 'sales.manage',
            'tech-support.manage',
            'reports.view', 'reports.export',
            'security.view', 'security.manage',
            'backup.run', 'backup.view',
        ]);



        // 5. Sales/CRM — Normal Staff tier by default (status/notes only, own
        // assigned customers — see CustomerPolicy/CustomerController). A
        // sales-crm user with crm_role='supervisor' (checked via
        // User::isCrmSupervisor(), not a Spatie permission) gets full edit
        // access back despite the role's permission set below.
        $salesCrm = Role::firstOrCreate(['name' => 'sales-crm', 'guard_name' => 'web']);
        $salesCrm->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create', 'crm.status-update',
            'sales.view', 'sales.manage',
            'reports.view',
        ]);

        // Technical Support
        $techSupport = Role::firstOrCreate(['name' => 'tech-support', 'guard_name' => 'web']);
        $techSupport->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create',
            'tech-support.manage',
        ]);

        // eBay Supervisor — can delete eBay records (offers, customer records, stores);
        // checked directly via hasRole() in CRM::canDeleteCrmRecords(), not permission-driven.
        $ebaySupervisor = Role::firstOrCreate(['name' => 'ebay-supervisor', 'guard_name' => 'web']);
        $ebaySupervisor->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create',
        ]);

        // Logistic Supervisor — can delete Logistics records (shipments, trucking companies);
        // checked directly via hasRole() in CRM::canDeleteCrmRecords(), not permission-driven.
        $logisticSupervisor = Role::firstOrCreate(['name' => 'logistic-supervisor', 'guard_name' => 'web']);
        $logisticSupervisor->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create',
        ]);

        // eBay Team — Normal Staff tier (status/notes only, own assigned
        // customers), distinct from ebay-supervisor which has delete rights.
        $ebayTeam = Role::firstOrCreate(['name' => 'ebay-team', 'guard_name' => 'web']);
        $ebayTeam->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create', 'crm.status-update',
            'sales.view', 'sales.manage',
            'reports.view',
        ]);

        // Logistic Team — Normal Staff tier (status/notes only, own assigned
        // customers), distinct from logistic-supervisor which has delete rights.
        $logisticTeam = Role::firstOrCreate(['name' => 'logistic-team', 'guard_name' => 'web']);
        $logisticTeam->syncPermissions([
            'dashboard.view',
            'crm.view', 'crm.create', 'crm.status-update',
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

        // 7. Boss — read-only oversight everywhere else, but can create CRM
        // customers directly (explicit exception, not a general edit/delete grant).
        $boss = Role::firstOrCreate(['name' => 'boss', 'guard_name' => 'web']);
        $boss->syncPermissions([
            'dashboard.view',
            'reports.view',
            'kanban.view', 'kanban.approve',
            'crm.view', 'crm.create',
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
