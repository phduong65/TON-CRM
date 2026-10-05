<?php

use Database\Seeders\RedzonePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        (new RedzonePermissionSeeder())->run();
    }

    public function down(): void
    {
        Permission::where('name', 'view-redzone')->where('guard_name', 'web')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
