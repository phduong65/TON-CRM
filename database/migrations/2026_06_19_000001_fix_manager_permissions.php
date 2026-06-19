<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $manager = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        if (! $manager) {
            return;
        }

        $toGrant = [
            'delete-penalties',
            'revoke-penalties',
            'delete-rewards',
            'revoke-rewards',
        ];

        foreach ($toGrant as $name) {
            $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (! $manager->hasPermissionTo($perm)) {
                $manager->givePermissionTo($perm);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $manager = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        if (! $manager) {
            return;
        }

        foreach (['delete-penalties', 'revoke-penalties', 'delete-rewards', 'revoke-rewards'] as $name) {
            $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($perm && $manager->hasPermissionTo($perm)) {
                $manager->revokePermissionTo($perm);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
