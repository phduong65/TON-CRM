<?php

namespace Database\Seeders;

use App\Models\RewardCategory;
use App\Models\RewardType;
use Illuminate\Database\Seeder;

class RewardTypesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Chung',
                'description' => 'Thưởng điểm áp dụng cho tất cả bộ phận',
                'types'       => [
                    ['name' => 'Ko đi trễ cả tháng',        'description' => 'Không đi trễ bất kỳ ca nào trong tháng',              'default_points' => 10],
                    ['name' => 'Ko vi phạm nội quy cả tháng','description' => 'Không có phiếu phạt nội quy nào trong tháng',         'default_points' => 10],
                    ['name' => 'Nhận ca đại xuất',           'description' => 'Nhận thêm ca khi nhân sự thiếu hoặc tình huống khẩn', 'default_points' => 5],
                    ['name' => 'KPI đạt',                    'description' => 'Hoàn thành chỉ tiêu KPI được giao trong tháng',       'default_points' => 15],
                    ['name' => 'Không review xấu',           'description' => 'Cả tháng không có review tiêu cực gắn tên nhân viên', 'default_points' => 10],
                ],
            ],
            [
                'name'        => 'Phục vụ',
                'description' => 'Thưởng điểm cho bộ phận phục vụ (Service)',
                'types'       => [
                    ['name' => 'Khách khen Facebook',  'description' => 'Khách để lại đánh giá / khen ngợi trên Facebook',     'default_points' => 5],
                    ['name' => 'Khách khen Google Review', 'description' => 'Khách để lại đánh giá 5 sao trên Google',         'default_points' => 10],
                ],
            ],
            [
                'name'        => 'Lếp xỉu',
                'description' => 'Thưởng điểm cho nhân viên trực ca lếp xỉu (hỗ trợ cuối ca)',
                'types'       => [
                    ['name' => 'Lếp xỉu mỗi lần',  'description' => 'Thưởng 2đ cho mỗi lần nhận ca lếp xỉu',             'default_points' => 2],
                    ['name' => 'Lếp xỉu 50+ lần',  'description' => 'Thưởng thêm 5đ bonus khi đạt 50+ lần lếp xỉu/tháng, trừ 400,000đ phí phát sinh', 'default_points' => 5],
                ],
            ],
            [
                'name'        => 'Bếp',
                'description' => 'Thưởng điểm cho bộ phận bếp (Kitchen)',
                'types'       => [
                    ['name' => 'Ko có món trả trong tháng', 'description' => 'Không có order bị trả lại hoặc khiếu nại món ăn', 'default_points' => 10],
                    ['name' => 'Food Cost tối ưu',          'description' => 'Đạt chỉ tiêu Food Cost tháng theo kế hoạch',     'default_points' => 10],
                ],
            ],
            [
                'name'        => 'Bar',
                'description' => 'Thưởng điểm cho bộ phận bar',
                'types'       => [
                    ['name' => 'Đào tạo nhân viên tốt', 'description' => 'Bartender/barista hướng dẫn đào tạo nhân viên mới hiệu quả', 'default_points' => 5],
                ],
            ],
            [
                'name'        => 'Marketing',
                'description' => 'Thưởng điểm cho bộ phận Marketing',
                'types'       => [
                    ['name' => 'Video đạt KPI',  'description' => 'Video/nội dung đăng tải đạt chỉ tiêu view/tương tác', 'default_points' => 5],
                    ['name' => 'Booking full',   'description' => 'Đạt tỉ lệ booking đầy bàn theo chỉ tiêu tháng',      'default_points' => 5],
                    ['name' => 'Ý tưởng hay',    'description' => 'Đề xuất ý tưởng marketing được duyệt và triển khai',  'default_points' => 10],
                ],
            ],
            [
                'name'        => 'IT',
                'description' => 'Thưởng điểm cho bộ phận IT',
                'types'       => [
                    ['name' => 'Giảm chi phí vận hành', 'description' => 'Tối ưu hệ thống giúp giảm chi phí vận hành so với kỳ trước', 'default_points' => 20],
                    ['name' => 'Đúng deadline',          'description' => 'Hoàn thành tất cả task IT đúng hoặc trước deadline',         'default_points' => 15],
                ],
            ],
            [
                'name'        => 'Thu Mua',
                'description' => 'Thưởng điểm cho bộ phận thu mua (Procurement)',
                'types'       => [
                    ['name' => 'Giảm giá NCC',      'description' => 'Thương lượng được giá nhà cung cấp thấp hơn so với tháng trước', 'default_points' => 10],
                    ['name' => 'Phát hiện sai sót', 'description' => 'Phát hiện hàng lỗi, gian lận hoặc sai sót trong đơn hàng',       'default_points' => 5],
                ],
            ],
        ];

        foreach ($categories as $catData) {
            $types = $catData['types'];
            unset($catData['types']);

            $category = RewardCategory::create([
                'name'        => $catData['name'],
                'description' => $catData['description'],
                'is_active'   => true,
            ]);

            foreach ($types as $typeData) {
                RewardType::create([
                    'reward_category_id' => $category->id,
                    'name'               => $typeData['name'],
                    'description'        => $typeData['description'],
                    'default_points'     => $typeData['default_points'],
                    'is_active'          => true,
                ]);
            }
        }
    }
}
