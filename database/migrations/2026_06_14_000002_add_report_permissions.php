<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view-reports', 'create-reports', 'approve-reports'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo(['view-reports', 'create-reports', 'approve-reports']);

        $manager = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        $manager?->givePermissionTo(['view-reports', 'create-reports', 'approve-reports']);

        $teamLeader = Role::where('name', 'team_leader')->where('guard_name', 'web')->first();
        $teamLeader?->givePermissionTo(['view-reports', 'create-reports']);

        $staff = Role::where('name', 'staff')->where('guard_name', 'web')->first();
        $staff?->givePermissionTo(['view-reports', 'create-reports']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view-reports', 'create-reports', 'approve-reports'] as $perm) {
            Permission::where('name', $perm)->where('guard_name', 'web')->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
