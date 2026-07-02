<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftsSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'code'  => 'CA-VP',
                'name'  => 'Ca văn phòng',
                'start_time' => '09:00',
                'end_time'   => '18:00',
                'is_overnight' => false,
                'break_minutes' => 60,
                'grace_late_minutes'  => 15,
                'grace_early_minutes' => 15,
                'work_mode' => 'onsite',
                'color' => '#2563eb',
            ],
            [
                'code'  => 'CA-NH-SANG',
                'name'  => 'Ca sáng nhà hàng',
                'start_time' => '11:00',
                'end_time'   => '15:00',
                'is_overnight' => false,
                'break_minutes' => 0,
                'grace_late_minutes'  => 10,
                'grace_early_minutes' => 10,
                'work_mode' => 'onsite',
                'color' => '#f59e0b',
            ],
            [
                'code'  => 'CA-NH-TOI',
                'name'  => 'Ca tối nhà hàng',
                'start_time' => '18:00',
                'end_time'   => '01:00',
                'is_overnight' => true,
                'break_minutes' => 0,
                'grace_late_minutes'  => 10,
                'grace_early_minutes' => 10,
                'work_mode' => 'onsite',
                'color' => '#7c3aed',
            ],
        ];

        foreach ($shifts as $data) {
            Shift::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['branch_id' => null, 'is_active' => true])
            );
        }
    }
}
