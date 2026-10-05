<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view-shift-coverage',
            'manage-shift-coverage',
            'view-attendance-alerts',
            'manage-attendance-alerts',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $allRoles = ['admin', 'manager', 'director'];
        foreach ($allRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        $teamLeader = Role::where('name', 'team_leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo(['view-shift-coverage', 'view-attendance-alerts']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = [
            'view-shift-coverage',
            'manage-shift-coverage',
            'view-attendance-alerts',
            'manage-attendance-alerts',
        ];

        foreach ($permissions as $perm) {
            Permission::where('name', $perm)->where('guard_name', 'web')->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
