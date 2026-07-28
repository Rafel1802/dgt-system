<?php
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Clear current user roles to avoid conflicts
DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->delete();

$mappings = [
    1 => [1], // user 1 gets super-admin
    7 => [5], // user 7 gets sales-crm
    3 => [6], 6 => [6, 12], 9 => [6], 10 => [6, 12], 12 => [6, 15], 13 => [6, 15],
    14 => [6], 15 => [6, 12], 16 => [6], 17 => [6, 12], 18 => [6], 19 => [6],
    20 => [6, 12], 21 => [6], 22 => [6], 23 => [6],
    5 => [7], // user 5 gets boss
    2 => [8], // user 2 gets admin-digital
    4 => [9], 11 => [9], // user 4, 11 get admin-crm
];

$roleNames = [
    1 => 'super-admin',
    5 => 'sales-crm',
    6 => 'digital-team',
    7 => 'boss',
    8 => 'admin-digital',
    9 => 'admin-crm',
    12 => 'social_admin',
    15 => 'social_qc',
];

foreach ($mappings as $userId => $roleIds) {
    $user = User::find($userId);
    if ($user) {
        foreach ($roleIds as $roleId) {
            if (isset($roleNames[$roleId])) {
                $user->assignRole($roleNames[$roleId]);
                echo "Assigned {$roleNames[$roleId]} to User $userId\n";
            }
        }
    }
}
