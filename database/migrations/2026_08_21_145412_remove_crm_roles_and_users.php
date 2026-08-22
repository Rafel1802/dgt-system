<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $crmRoles = [
            'admin-crm',
            'sales-crm',
            'ebay-supervisor',
            'logistic-supervisor',
            'ebay-team',
            'logistic-team'
        ];

        // Find users that have any of these CRM roles
        $users = User::whereHas('roles', function ($query) use ($crmRoles) {
            $query->whereIn('name', $crmRoles);
        })->get();

        foreach ($users as $user) {
            $user->delete();
        }

        // Now delete the roles
        Role::whereIn('name', $crmRoles)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 
    }
};
