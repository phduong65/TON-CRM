<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view-reward-categories',
            'create-reward-categories',
            'edit-reward-categories',
            'delete-reward-categories',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo($permissions);

        $manager = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        $manager?->givePermissionTo(['view-reward-categories']);

        $teamLeader = Role::where('name', 'team_leader')->where('guard_name', 'web')->first();
        $teamLeader?->givePermissionTo(['view-reward-categories']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'view-reward-categories',
            'create-reward-categories',
            'edit-reward-categories',
            'delete-reward-categories',
        ])->where('guard_name', 'web')->delete();
    }
};
