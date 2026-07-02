<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PcrmSeeder::class);
        $this->call(ViolationsSeeder::class);
        $this->call(RewardTypesSeeder::class);
        $this->call(RealEmployeeSeeder::class);
        $this->call(UserManagementSeeder::class);
        $this->call(ShiftAttendancePermissionsSeeder::class);
        $this->call(ShiftsSeeder::class);
        $this->call(LeaveSwapPermissionsSeeder::class);
        $this->call(StaffRequestsPermissionsSeeder::class);
        $this->call(LogViewerSeeder::class);
        $this->call(ExportPermissionsSeeder::class);
        $this->call(HolidaysPermissionsSeeder::class);
    }
}
