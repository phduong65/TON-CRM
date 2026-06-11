<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $rewardPermissions = [
            'view-rewards', 'create-rewards', 'delete-rewards', 'approve-rewards',
            'view-reward-types', 'create-reward-types', 'edit-reward-types', 'delete-reward-types',
        ];

        foreach ($rewardPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Admin gets all
        $admin = Role::findByName('admin', 'web');
        $admin?->givePermissionTo($rewardPermissions);

        // Manager: tạo + duyệt thưởng, xem loại thưởng
        $manager = Role::findByName('manager', 'web');
        $manager?->givePermissionTo([
            'view-rewards', 'create-rewards', 'approve-rewards',
            'view-reward-types',
        ]);

        // Team leader: tạo phiếu thưởng
        $teamLeader = Role::findByName('team_leader', 'web');
        $teamLeader?->givePermissionTo([
            'view-rewards', 'create-rewards',
            'view-reward-types',
        ]);

        // Staff: xem thưởng của mình
        $staff = Role::findByName('staff', 'web');
        $staff?->givePermissionTo(['view-rewards']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $rewardPermissions = [
            'view-rewards', 'create-rewards', 'delete-rewards', 'approve-rewards',
            'view-reward-types', 'create-reward-types', 'edit-reward-types', 'delete-reward-types',
        ];

        foreach ($rewardPermissions as $perm) {
            Permission::where('name', $perm)->where('guard_name', 'web')->delete();
        }
    }
};
