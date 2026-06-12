<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\Violation;
use Illuminate\Database\Seeder;

class ViolationsSeeder extends Seeder
{
    public function run(): void
    {
        // Xoá dữ liệu cũ từ PcrmSeeder (placeholder)
        Violation::query()->delete();
        Regulation::query()->delete();

        $regulations = [

            // ── 1. KỶ LUẬT GIỜ GIẤC ───────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế kỷ luật giờ giấc',
                    'description' => 'Xử phạt đối với hành vi đi trễ, nghỉ không phép, vắng mặt không báo cáo',
                ],
                'violations' => [
                    ['name' => 'Đến trễ 1–5 phút',                    'severity' => 'low',      'penalty_type' => 'points', 'points_deducted' => 1,  'money_deducted' => 0,      'description' => 'Đến trễ dưới 5 phút'],
                    ['name' => 'Đến trễ 5–15 phút',                   'severity' => 'low',      'penalty_type' => 'points', 'points_deducted' => 3,  'money_deducted' => 0,      'description' => 'Đến trễ từ 5 đến 15 phút'],
                    ['name' => 'Đến trễ 15–30 phút',                  'severity' => 'medium',   'penalty_type' => 'points', 'points_deducted' => 5,  'money_deducted' => 0,      'description' => 'Đến trễ từ 15 đến 30 phút'],
                    ['name' => 'Đến trễ trên 30 phút',                'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0,      'description' => 'Đến trễ hơn 30 phút'],
                    ['name' => 'Nghỉ báo trước nhưng chưa được duyệt','severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0,      'description' => 'Tự ý nghỉ khi đơn nghỉ phép chưa được quản lý phê duyệt'],
                    ['name' => 'Nghỉ sai giờ làm',                    'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 15, 'money_deducted' => 0,      'description' => 'Về sớm hoặc rời vị trí không đúng lịch mà chưa được phép'],
                    ['name' => 'No show — vắng không phép',           'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 25, 'money_deducted' => 0,      'description' => 'Vắng mặt hoàn toàn không báo và không có lý do chính đáng'],
                ],
            ],

            // ── 2. TÀI SẢN ────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế quản lý tài sản',
                    'description' => 'Xử phạt khi nhân viên làm hỏng, mất hoặc hủy hoại tài sản của công ty',
                ],
                'violations' => [
                    ['name' => 'Bể/hỏng tài sản',   'severity' => 'high',     'penalty_type' => 'both',   'points_deducted' => 5, 'money_deducted' => 400000, 'description' => 'Phạt 400,000đ + trừ 5đ (CN). Tài sản bị bể hoặc hỏng do lỗi nhân viên'],
                    ['name' => 'Mất/hủy tài sản',   'severity' => 'critical', 'penalty_type' => 'both',   'points_deducted' => 5, 'money_deducted' => 0,      'description' => 'Phạt theo giá trị thật của tài sản + trừ 5đ (CN), QL trừ thêm 2đ. Nhập số tiền thực tế khi tạo phiếu phạt'],
                ],
            ],

            // ── 3. LỖI PHỤC VỤ ────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ phục vụ',
                    'description' => 'Xử phạt lỗi trong quá trình phục vụ khách hàng tại bàn và xử lý đánh giá',
                ],
                'violations' => [
                    ['name' => 'Sai món',        'severity' => 'medium',   'penalty_type' => 'both', 'points_deducted' => 5,  'money_deducted' => 200000, 'description' => 'Phạt 200,000đ + trừ 5đ (CN). Gọi sai món cho khách'],
                    ['name' => 'Sai bàn',        'severity' => 'low',      'penalty_type' => 'both', 'points_deducted' => 2,  'money_deducted' => 50000,  'description' => 'Phạt 50,000đ + trừ 2đ (CN). Mang món ra nhầm bàn'],
                    ['name' => 'Review xấu',     'severity' => 'critical', 'penalty_type' => 'both', 'points_deducted' => 15, 'money_deducted' => 200000, 'description' => 'Phạt 200,000đ + trừ 15đ (CN). Khách để lại đánh giá tiêu cực gắn trực tiếp với nhân viên'],
                ],
            ],

            // ── 4. LỖI BẾP ────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ bếp',
                    'description' => 'Xử phạt lỗi chế biến, kiểm soát nguyên liệu và quản lý dụng cụ bếp',
                ],
                'violations' => [
                    ['name' => 'Ra món sai',             'severity' => 'high',   'penalty_type' => 'both', 'points_deducted' => 7,  'money_deducted' => 200000, 'description' => 'Phạt 200,000đ + trừ 7đ (CN). Bếp ra món không đúng order'],
                    ['name' => 'Món bị trả',             'severity' => 'high',   'penalty_type' => 'both', 'points_deducted' => 10, 'money_deducted' => 200000, 'description' => 'Phạt 200,000đ + trừ 10đ (CN). Khách trả món do lỗi chế biến'],
                    ['name' => 'Quản lý tái sử dụng DC không đúng quy định', 'severity' => 'medium', 'penalty_type' => 'both', 'points_deducted' => 5, 'money_deducted' => 100000, 'description' => 'Phạt 100,000đ + trừ 5đ (CN). Dùng lại dụng cụ không đúng quy trình vệ sinh'],
                ],
            ],

            // ── 5. LỖI BAR ────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ bar',
                    'description' => 'Xử phạt lỗi pha chế sai công thức hoặc để khách trả đồ uống',
                ],
                'violations' => [
                    ['name' => 'Sai công thức pha chế', 'severity' => 'high', 'penalty_type' => 'both', 'points_deducted' => 7,  'money_deducted' => 100000, 'description' => 'Phạt 100,000đ + trừ 7đ (CN). Pha chế không đúng recipe chuẩn'],
                    ['name' => 'Khách trả đồ uống',     'severity' => 'high', 'penalty_type' => 'both', 'points_deducted' => 10, 'money_deducted' => 200000, 'description' => 'Phạt 200,000đ + trừ 10đ (CN). Khách không chấp nhận đồ uống do lỗi pha chế'],
                ],
            ],

            // ── 6. HÀNH VI & THÁI ĐỘ ─────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế hành vi & thái độ',
                    'description' => 'Xử phạt hành vi không phù hợp với khách hàng, đồng nghiệp và tiêu chuẩn dịch vụ',
                ],
                'violations' => [
                    ['name' => 'Thái độ tranh cãi với đồng nghiệp', 'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Cãi vã, gây mất đoàn kết nội bộ'],
                    ['name' => 'Cãi khách',                          'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 20, 'money_deducted' => 0, 'description' => 'Tranh cãi trực tiếp với khách hàng'],
                    ['name' => 'Thiếu tôn trọng khách hàng',         'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 20, 'money_deducted' => 0, 'description' => 'Hành vi, lời nói thể hiện sự thiếu tôn trọng với khách'],
                    ['name' => 'Thái độ không kiềm chế cuối ca',     'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Mất bình tĩnh, biểu hiện tiêu cực trong giờ cao điểm cuối ca'],
                    ['name' => 'Tự ý giảm giá cho khách',            'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Tự ý áp dụng giảm giá, khuyến mãi khi chưa được phép'],
                    ['name' => 'Chốt lệch tiền mặt cuối ca',         'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 30, 'money_deducted' => 0, 'description' => 'Số tiền mặt thực tế không khớp với hệ thống khi chốt ca'],
                ],
            ],

            // ── 7. DỤNG CỤ & THIẾT BỊ ─────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế sử dụng dụng cụ & thiết bị',
                    'description' => 'Xử phạt khi nhân viên không tắt thiết bị điện sau ca làm việc',
                ],
                'violations' => [
                    ['name' => 'Không tắt điện sau ca',     'severity' => 'low', 'penalty_type' => 'points', 'points_deducted' => 2, 'money_deducted' => 0, 'description' => 'Quên tắt đèn hoặc ổ điện khu vực phụ trách'],
                    ['name' => 'Không tắt máy lạnh sau ca', 'severity' => 'low', 'penalty_type' => 'points', 'points_deducted' => 2, 'money_deducted' => 0, 'description' => 'Quên tắt điều hòa khi hết ca'],
                    ['name' => 'Không tắt quạt sau ca',     'severity' => 'low', 'penalty_type' => 'points', 'points_deducted' => 2, 'money_deducted' => 0, 'description' => 'Quên tắt quạt khu vực phụ trách'],
                    ['name' => 'Không tắt nhạc sau ca',     'severity' => 'low', 'penalty_type' => 'points', 'points_deducted' => 2, 'money_deducted' => 0, 'description' => 'Quên tắt hệ thống âm thanh khi hết ca'],
                ],
            ],

            // ── 8. VỆ SINH ────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế vệ sinh khu vực làm việc',
                    'description' => 'Xử phạt khi khu vực phụ trách không đảm bảo tiêu chuẩn vệ sinh',
                ],
                'violations' => [
                    ['name' => 'Quầy/khu vực không sạch', 'severity' => 'medium', 'penalty_type' => 'points', 'points_deducted' => 5, 'money_deducted' => 0, 'description' => 'Quầy bar, bàn pha chế hoặc khu vực được giao không được vệ sinh đúng chuẩn'],
                    ['name' => 'Nhà vệ sinh bẩn',          'severity' => 'medium', 'penalty_type' => 'points', 'points_deducted' => 5, 'money_deducted' => 0, 'description' => 'Nhà vệ sinh trong ca phụ trách không được dọn dẹp đúng lịch'],
                    ['name' => 'Không đổ rác',             'severity' => 'medium', 'penalty_type' => 'points', 'points_deducted' => 5, 'money_deducted' => 0, 'description' => 'Không đổ rác đúng giờ hoặc để rác tràn ra ngoài thùng'],
                ],
            ],

            // ── 9. QUẢN LÝ ────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế trách nhiệm quản lý',
                    'description' => 'Xử phạt quản lý/team leader khi không hoàn thành trách nhiệm đào tạo và xử lý sự cố',
                ],
                'violations' => [
                    ['name' => 'Không đào tạo nhân viên mới', 'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Quản lý/TL không thực hiện onboarding và đào tạo nhân viên mới theo quy trình'],
                    ['name' => 'Không xử lý complaint',       'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 15, 'money_deducted' => 0, 'description' => 'Quản lý không tiếp nhận hoặc giải quyết khiếu nại của khách hàng'],
                ],
            ],

            // ── 10. IT ────────────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ IT',
                    'description' => 'Xử phạt vi phạm về tiến độ, bảo mật dữ liệu và quản trị hệ thống',
                ],
                'violations' => [
                    ['name' => 'Không đạt tiến độ giao việc',   'severity' => 'high',   'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Task IT không hoàn thành đúng deadline đã cam kết'],
                    ['name' => 'Không backup dữ liệu',           'severity' => 'medium', 'penalty_type' => 'points', 'points_deducted' => 3,  'money_deducted' => 0, 'description' => 'Bỏ qua lịch backup định kỳ theo quy trình'],
                    ['name' => 'Cấp sai quyền truy cập',        'severity' => 'low',    'penalty_type' => 'points', 'points_deducted' => 2,  'money_deducted' => 0, 'description' => 'Gán quyền hệ thống sai vai trò hoặc cấp vượt phạm vi cho phép'],
                    ['name' => 'Không xử lý sự cố hệ thống',   'severity' => 'medium', 'penalty_type' => 'points', 'points_deducted' => 5,  'money_deducted' => 0, 'description' => 'Không phản hồi hoặc xử lý incident trong thời gian quy định'],
                ],
            ],

            // ── 11. MARKETING ─────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ Marketing',
                    'description' => 'Xử phạt vi phạm về nội dung, chính tả và quản lý tài sản truyền thông',
                ],
                'violations' => [
                    ['name' => 'Đăng bài sai/nội dung không phù hợp', 'severity' => 'high',     'penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'description' => 'Đăng nội dung sai thông tin, thiếu chuyên nghiệp hoặc vi phạm brand guideline'],
                    ['name' => 'Lỗi chính tả trong nội dung',         'severity' => 'low',      'penalty_type' => 'points', 'points_deducted' => 2,  'money_deducted' => 0, 'description' => 'Bài đăng có lỗi chính tả, ngữ pháp sau khi đã publish'],
                    ['name' => 'Mất dữ liệu/tài sản media',           'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 20, 'money_deducted' => 0, 'description' => 'Làm mất file ảnh, video hoặc tài nguyên thiết kế của dự án'],
                ],
            ],

            // ── 12. KẾ TOÁN ───────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế nghiệp vụ kế toán',
                    'description' => 'Xử phạt vi phạm về nhập liệu, chứng từ, báo cáo tài chính và hồ sơ nhân sự',
                ],
                'violations' => [
                    ['name' => 'Nhập sai dữ liệu kế toán',          'severity' => 'medium',   'penalty_type' => 'points', 'points_deducted' => 5,  'money_deducted' => 0, 'description' => 'Nhập sai số liệu vào phần mềm kế toán hoặc hệ thống'],
                    ['name' => 'Thiếu chứng từ thanh toán',          'severity' => 'medium',   'penalty_type' => 'points', 'points_deducted' => 5,  'money_deducted' => 0, 'description' => 'Hóa đơn, biên lai hoặc chứng từ liên quan bị thiếu khi đối soát'],
                    ['name' => 'Tự ý thay đổi thông tin NCC',        'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 20, 'money_deducted' => 0, 'description' => 'Thay đổi thông tin nhà cung cấp trong hệ thống khi chưa được phê duyệt'],
                    ['name' => 'Chậm nộp BHXH',                     'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 50, 'money_deducted' => 0, 'description' => 'Nộp báo cáo hoặc đóng bảo hiểm xã hội trễ hạn quy định pháp luật'],
                    ['name' => 'Mất hồ sơ nhân sự/tài liệu',        'severity' => 'critical', 'penalty_type' => 'points', 'points_deducted' => 20, 'money_deducted' => 0, 'description' => 'Hồ sơ nhân viên, hợp đồng hoặc tài liệu tài chính bị thất lạc'],
                ],
            ],
        ];

        foreach ($regulations as $item) {
            $regulation = Regulation::create(array_merge($item['regulation'], ['is_active' => true]));

            foreach ($item['violations'] as $violationData) {
                Violation::create(array_merge($violationData, [
                    'regulation_id' => $regulation->id,
                    'is_active'     => true,
                ]));
            }
        }
    }
}
