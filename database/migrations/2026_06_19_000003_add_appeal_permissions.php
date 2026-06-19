<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $createAppeals = Permission::firstOrCreate(['name' => 'create-appeals', 'guard_name' => 'web']);
        $viewAppeals   = Permission::firstOrCreate(['name' => 'view-appeals',   'guard_name' => 'web']);
        $reviewAppeals = Permission::firstOrCreate(['name' => 'review-appeals', 'guard_name' => 'web']);

        foreach (['admin', 'manager', 'team_leader', 'staff'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo($createAppeals)) {
                $role->givePermissionTo($createAppeals);
            }
        }

        foreach (['admin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                if (! $role->hasPermissionTo($viewAppeals))   { $role->givePermissionTo($viewAppeals); }
                if (! $role->hasPermissionTo($reviewAppeals)) { $role->givePermissionTo($reviewAppeals); }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['create-appeals', 'view-appeals', 'review-appeals'] as $name) {
            Permission::where('name', $name)->where('guard_name', 'web')->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
