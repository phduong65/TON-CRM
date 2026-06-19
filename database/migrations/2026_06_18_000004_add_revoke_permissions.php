<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $revokePenalties = Permission::firstOrCreate(['name' => 'revoke-penalties', 'guard_name' => 'web']);
        $revokeRewards   = Permission::firstOrCreate(['name' => 'revoke-rewards',   'guard_name' => 'web']);

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([$revokePenalties, $revokeRewards]);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'revoke-penalties')->delete();
        Permission::where('name', 'revoke-rewards')->delete();
    }
};
