<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ShiftCoverageRequirement;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ShiftCoverageRequirementsSeeder extends Seeder
{
    public function run(): void
    {
        // CN 43 Nguyễn Huệ (Spec 4.4)
        $branch = Branch::where('name', 'like', '%43 Nguyễn Huệ%')->first() ?? Branch::first();
        if (!$branch) {
            return;
        }

        $restaurantTeam = Team::where('branch_id', $branch->id)->where('name', 'like', '%Nhà hàng%')->first()
            ?? Team::where('name', 'like', '%Nhà hàng%')->first();
        $kitchenTeam = Team::where('branch_id', $branch->id)->where('name', 'like', '%Bếp%')->first()
            ?? Team::where('name', 'like', '%Bếp%')->first();

        $allDays = [1, 2, 3, 4, 5, 6, 7];
        $effectiveFrom = now()->startOfYear()->toDateString();

        if ($restaurantTeam) {
            ShiftCoverageRequirement::firstOrCreate(
                [
                    'branch_id'  => $branch->id,
                    'team_id'    => $restaurantTeam->id,
                    'name'       => 'Ca sáng — Nhà hàng',
                    'start_time' => '11:00',
                    'end_time'   => '15:00',
                ],
                [
                    'days_of_week'    => $allDays,
                    'minimum_staff'   => 3,
                    'target_staff'    => 3,
                    'effective_from'  => $effectiveFrom,
                    'is_active'       => true,
                ]
            );

            ShiftCoverageRequirement::firstOrCreate(
                [
                    'branch_id'  => $branch->id,
                    'team_id'    => $restaurantTeam->id,
                    'name'       => 'Ca tối — Nhà hàng',
                    'start_time' => '18:00',
                    'end_time'   => '00:00',
                ],
                [
                    'days_of_week'    => $allDays,
                    'minimum_staff'   => 5,
                    'target_staff'    => 5,
                    'effective_from'  => $effectiveFrom,
                    'is_active'       => true,
                ]
            );
        }

        if ($kitchenTeam) {
            ShiftCoverageRequirement::firstOrCreate(
                [
                    'branch_id'  => $branch->id,
                    'team_id'    => $kitchenTeam->id,
                    'name'       => 'Ca sáng — Bếp',
                    'start_time' => '11:00',
                    'end_time'   => '15:00',
                ],
                [
                    'days_of_week'    => $allDays,
                    'minimum_staff'   => 2,
                    'target_staff'    => 3,
                    'effective_from'  => $effectiveFrom,
                    'is_active'       => true,
                ]
            );

            ShiftCoverageRequirement::firstOrCreate(
                [
                    'branch_id'  => $branch->id,
                    'team_id'    => $kitchenTeam->id,
                    'name'       => 'Ca tối — Bếp',
                    'start_time' => '18:00',
                    'end_time'   => '00:00',
                ],
                [
                    'days_of_week'    => $allDays,
                    'minimum_staff'   => 4,
                    'target_staff'    => 4,
                    'effective_from'  => $effectiveFrom,
                    'is_active'       => true,
                ]
            );
        }
    }
}
