<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class SetupBlogReportPermission extends Command
{
    protected $signature = 'app:setup-blog-report-permission';
    protected $description = 'Setup permissions for Blog Reports';

    public function handle()
    {
        $permissionName = 'view-blog-reports';

        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $this->info("Permission {$permissionName} ensured.");

        // Try to assign to superadmin role (which might be 'boss' or 'superadmin' here)
        $superadmin = Role::whereIn('name', ['superadmin', 'boss', 'admin'])->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($permission);
            $this->info("Assigned to role: {$superadmin->name}");
        }

        // Assign to specific users
        $usernames = ['sreypich', 'lyza', 'dara'];
        $users = User::whereIn('username', $usernames)->get();

        foreach ($users as $user) {
            $user->givePermissionTo($permission);
            $this->info("Assigned to user: {$user->username}");
        }

        $this->info('Done!');
    }
}
