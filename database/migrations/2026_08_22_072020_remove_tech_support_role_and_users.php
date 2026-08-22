<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveTechSupportRoleAndUsers extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('name', 'tech-support')->first();

        if ($role) {
            // Digital roles — users with these should NOT be deleted
            $digitalRoleIds = DB::table('roles')
                ->whereIn('name', ['super-admin', 'admin-digital', 'digital-team', 'social_admin', 'social_qc', 'supervisor', 'boss'])
                ->pluck('id');

            $techSupportUserIds = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\Models\User')
                ->pluck('model_id');

            foreach ($techSupportUserIds as $userId) {
                $hasDigitalRole = DB::table('model_has_roles')
                    ->where('model_id', $userId)
                    ->where('model_type', 'App\Models\User')
                    ->whereIn('role_id', $digitalRoleIds)
                    ->exists();

                if (!$hasDigitalRole) {
                    // Remove all role assignments for this user
                    DB::table('model_has_roles')
                        ->where('model_id', $userId)
                        ->where('model_type', 'App\Models\User')
                        ->delete();

                    // Soft-delete and deactivate
                    DB::table('users')
                        ->where('id', $userId)
                        ->update(['deleted_at' => now(), 'is_active' => false]);
                }
            }

            // Remove permissions from the tech-support role then delete the role
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        // Remove any tech-support.* permissions
        $permIds = DB::table('permissions')
            ->where('name', 'like', 'tech-support%')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }

    public function down(): void {}
}
