<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate([
            'name'       => 'import-attendance',
            'guard_name' => 'web',
        ]);

        // Grant to admin and manager
        foreach (['admin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo('import-attendance')) {
                $role->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::where('name', 'import-attendance')->where('guard_name', 'web')->first();
        $perm?->delete();
    }
};
