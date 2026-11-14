<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $adminDigitalRole = Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);

        $dara = User::whereRaw('LOWER(username) = ?', ['dara'])->first();
        if ($dara && !$dara->hasRole('admin-digital')) {
            $dara->assignRole($adminDigitalRole);
        }

        $kim = User::whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(username)'), ['kim', 'kimoun'])->first();
        if ($kim && !$kim->hasRole('admin-digital')) {
            $kim->assignRole($adminDigitalRole);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve assigned administrative capabilities
    }
};

