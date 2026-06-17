<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\Violation;
use Illuminate\Database\Seeder;

class ViolationsSeeder extends Seeder
{
    // Ánh xạ mức độ → điểm trừ (cố định, không nhập tay)
    private const POINTS_MAP = [
        'low'      => 1,   // Nhẹ
        'medium'   => 3,   // Trung bình
        'high'     => 5,   // Nặng
        'critical' => 10,  // Nghiêm trọng
        'extreme'  => 20,  // Đặc biệt nghiêm trọng
    ];

    public function run(): void
    {
        Violation::query()->delete();
        Regulation::query()->delete();

        // Cấu trúc mỗi violation: name, severity, penalty_type, money_deducted, description
        // points_deducted được tự động tính từ severity theo POINTS_MAP

        $regulations = [

            // ── 1. GIỜ GIẤC & KỶ LUẬT LAO ĐỘNG ─────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Giờ Giấc & Kỷ Luật Lao Động',
                    'description' => 'Xử phạt đối với hành vi đi trễ, về sớm, nghỉ phép, nghỉ đột xuất, chấm công, đồng phục và tuân thủ nội quy',
                ],
                'violations' => [
                    // Đi trễ & về sớm
                    ['name' => 'Đi trễ dưới 5 phút',                             'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Đi trễ dưới 5 phút'],
                    ['name' => 'Đi trễ 5–15 phút',                               'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Có mặt trễ từ 5 đến 15 phút'],
                    ['name' => 'Đi trễ 15–30 phút',                              'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Có mặt trễ từ 15 đến 30 phút'],
                    ['name' => 'Đi trễ trên 30 phút',                            'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Có mặt trễ trên 30 phút'],
                    ['name' => 'Đi trễ nhiều lần trong tuần',                    'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Từ lần thứ 3 trở lên đi trễ trong cùng tuần'],
                    ['name' => 'Về sớm dưới 15 phút',                            'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Rời vị trí trước giờ làm dưới 15 phút'],
                    ['name' => 'Về sớm trên 15 phút',                            'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Rời vị trí trước giờ làm trên 15 phút'],
                    ['name' => 'Tự ý bỏ vị trí trong ca',                        'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Rời khu vực làm việc mà không báo cáo quản lý'],
                    // Nghỉ phép & nghỉ đột xuất
                    ['name' => 'Nghỉ báo trước nhưng chưa được duyệt',           'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Tự ý nghỉ khi chưa có xác nhận phê duyệt từ quản lý'],
                    ['name' => 'Nghỉ gấp báo dưới 4 giờ trước ca',              'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Báo nghỉ quá sát giờ làm, dưới 4 giờ trước khi ca bắt đầu'],
                    ['name' => 'Nghỉ đột xuất không có lý do hợp lý',           'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không cung cấp lý do chính đáng khi nghỉ đột xuất'],
                    ['name' => 'No Show',                                          'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không đi làm, không báo cáo'],
                    ['name' => 'No Show cuối tuần hoặc ngày lễ',                 'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không đi làm vào ca cao điểm cuối tuần hoặc ngày lễ mà không báo'],
                    ['name' => 'Nghỉ quá số ngày quy định trong tháng',          'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Số ngày nghỉ vượt giới hạn cho phép theo quy định công ty'],
                    // Chấm công
                    ['name' => 'Quên chấm công vào ca',                           'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không thực hiện check-in khi bắt đầu ca làm'],
                    ['name' => 'Quên chấm công ra ca',                            'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không thực hiện check-out khi kết thúc ca làm'],
                    ['name' => 'Quên chấm công từ 3 lần trở lên trong tháng',   'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Từ lần thứ 3 trở lên quên chấm công trong cùng một tháng'],
                    ['name' => 'Nhờ người chấm công hộ',                          'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Nhờ đồng nghiệp chấm công thay, gian lận thời gian làm việc'],
                    ['name' => 'Chấm công hộ người khác',                         'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hỗ trợ đồng nghiệp gian lận bằng cách chấm công thay'],
                    ['name' => 'Sửa dữ liệu chấm công sai sự thật',             'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Cố tình khai sai giờ làm hoặc chỉnh sửa dữ liệu chấm công'],
                    // Đồng phục & tác phong
                    ['name' => 'Không mặc đồng phục đúng quy định',             'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Mặc sai áo hoặc quần đồng phục theo quy định của công ty'],
                    ['name' => 'Đồng phục nhăn nhúm hoặc bẩn',                  'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Mặc đồng phục thiếu chỉn chu, nhăn nhúm hoặc có vết bẩn rõ ràng'],
                    ['name' => 'Không đeo bảng tên trong ca',                    'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không đeo bảng tên nhân viên khiến khách không nhận diện được'],
                    ['name' => 'Mang dép không đúng quy định',                   'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Dùng dép hoặc giày không đúng tiêu chuẩn an toàn và đồng bộ quy định'],
                    ['name' => 'Tóc tai không gọn gàng',                          'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Kiểu tóc không phù hợp hoặc không gọn gàng khi tiếp xúc với khách hàng'],
                    ['name' => 'Có mùi cơ thể ảnh hưởng môi trường làm việc',  'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không đảm bảo vệ sinh cá nhân, có mùi cơ thể gây ảnh hưởng đến khách và đồng nghiệp'],
                    ['name' => 'Hút thuốc trong khu vực làm việc',              'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hút thuốc trong khu vực làm việc hoặc nơi không được phép'],
                    ['name' => 'Uống rượu bia trước hoặc trong ca',             'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Sử dụng rượu bia trước hoặc trong ca làm, ảnh hưởng đến hiệu suất công việc'],
                    // Tuân thủ nội quy
                    ['name' => 'Không tham gia briefing đầu ca',                 'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Vắng mặt buổi họp đầu ca mà không có lý do chính đáng'],
                    ['name' => 'Không đọc/nắm thông báo của công ty',           'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Bỏ sót thông báo vận hành gây ảnh hưởng đến công việc'],
                    ['name' => 'Không thực hiện checklist đầu ca',              'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Bỏ qua quy trình kiểm tra và chuẩn bị theo checklist khi bắt đầu ca'],
                    ['name' => 'Không thực hiện checklist cuối ca',             'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Bỏ qua quy trình kiểm tra và dọn dẹp theo checklist khi kết thúc ca'],
                    ['name' => 'Không hoàn thành task được giao trong ca',      'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không hoàn thành nhiệm vụ được giao mà không có lý do chính đáng'],
                    ['name' => 'Không phản hồi quản lý khi được yêu cầu',      'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Gây gián đoạn vận hành do không phản hồi hoặc phản hồi trễ yêu cầu từ quản lý'],
                    ['name' => 'Từ chối công việc trong phạm vi trách nhiệm',   'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không hợp tác, từ chối thực hiện công việc nằm trong phạm vi trách nhiệm vị trí'],
                    ['name' => 'Không tuân thủ SOP đã được đào tạo',           'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Thực hiện sai quy trình chuẩn đã được đào tạo và ký nhận'],
                    ['name' => 'Tái phạm cùng một lỗi 3 lần trong tháng',      'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không cải thiện sau khi được nhắc nhở, tái phạm cùng lỗi từ 3 lần trở lên trong tháng'],
                ],
            ],

            // ── 2. VẬN HÀNH & NGHIỆP VỤ ─────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Vận Hành & Nghiệp Vụ',
                    'description' => 'Xử phạt lỗi trong quy trình phục vụ, bar, bếp và tiêu chuẩn phục vụ khách hàng',
                ],
                'violations' => [
                    // Phục vụ
                    ['name' => 'Phục vụ chậm so với tiêu chuẩn',                 'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Thời gian phục vụ vượt quá tiêu chuẩn quy định mà không có lý do hợp lý'],
                    ['name' => 'Mang món nhầm bàn',                               'severity' => 'medium',   'penalty_type' => 'both',   'money_deducted' => 50000,  'description' => 'Mang món ăn hoặc đồ uống đến nhầm bàn khách'],
                    ['name' => 'Gọi sai món cho khách',                           'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Nhập sai order của khách dẫn đến bếp/bar ra sai món'],
                    ['name' => 'Không tuân thủ quy trình phục vụ',               'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Bỏ qua hoặc thực hiện sai các bước trong quy trình phục vụ chuẩn'],
                    ['name' => 'Không hoàn thành MISE EN PLACE trước ca',        'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Không chuẩn bị đầy đủ vật dụng và nguyên liệu theo checklist trước khi ca bắt đầu'],
                    ['name' => 'Bàn giao ca không đúng quy trình',                'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Không thực hiện bàn giao ca đúng quy trình: ghi chú, kiểm kho, thông báo cho ca sau'],
                    // Bar
                    ['name' => 'Pha chế sai công thức',                           'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 100000, 'description' => 'Pha chế đồ uống không đúng recipe hoặc tỷ lệ chuẩn quy định'],
                    ['name' => 'Khách trả đồ uống do lỗi pha chế',               'severity' => 'critical', 'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Khách từ chối nhận hoặc trả lại đồ uống do chất lượng pha chế không đạt'],
                    ['name' => 'Lãng phí nguyên liệu pha chế',                    'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Sử dụng nguyên liệu bar vượt mức định lượng chuẩn không có lý do hợp lý'],
                    // Bếp (trong vận hành)
                    ['name' => 'Ra món không đúng order',                          'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Bếp ra món không khớp với order về loại, số lượng hoặc yêu cầu đặc biệt của khách'],
                    ['name' => 'Món bị trả do lỗi phục vụ',                      'severity' => 'critical', 'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Khách từ chối nhận hoặc trả lại món do lỗi trong quá trình phục vụ'],
                    // Tiêu chuẩn phục vụ khách hàng
                    ['name' => 'Không chào hỏi khách theo chuẩn thương hiệu',    'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Không thực hiện nghi thức chào hỏi và đón tiếp khách đúng chuẩn thương hiệu'],
                    ['name' => 'Không xử lý yêu cầu khách kịp thời',             'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Bỏ qua hoặc chậm trễ xử lý yêu cầu hợp lý từ khách hàng'],
                    ['name' => 'Khách để lại review tiêu cực về nhân viên',      'severity' => 'critical', 'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Khách đánh giá tiêu cực gắn trực tiếp với hành vi hoặc thái độ của nhân viên'],
                ],
            ],

            // ── 3. TÀI SẢN & THIẾT BỊ ───────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Tài Sản & Thiết Bị',
                    'description' => 'Xử phạt khi nhân viên quản lý tài sản sai quy định, làm hư hỏng hoặc mất mát tài sản công ty',
                ],
                'violations' => [
                    ['name' => 'Sử dụng thiết bị sai mục đích',                  'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Dùng dụng cụ, thiết bị của công ty cho mục đích cá nhân hoặc không đúng chức năng'],
                    ['name' => 'Không tắt thiết bị điện sau ca',                 'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Quên tắt đèn, điều hòa, quạt hoặc thiết bị điện khác khi kết thúc ca'],
                    ['name' => 'Bảo quản dụng cụ không đúng quy định',          'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Không cất giữ, vệ sinh hoặc bảo quản dụng cụ theo quy trình sau khi sử dụng'],
                    ['name' => 'Không báo cáo thiết bị hư hỏng kịp thời',       'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Phát hiện thiết bị có dấu hiệu hỏng hóc nhưng không báo cáo kịp thời cho quản lý'],
                    ['name' => 'Làm hỏng tài sản do sơ suất',                   'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 400000, 'description' => 'Tài sản bị hỏng do lỗi sơ suất. Phạt tiền theo giá trị thực tế nếu lớn hơn mức cơ bản'],
                    ['name' => 'Làm mất tài sản công ty',                        'severity' => 'critical', 'penalty_type' => 'both',   'money_deducted' => 0,      'description' => 'Tài sản bị mất do thiếu trách nhiệm. Nhập số tiền bồi thường theo giá trị thực tế khi tạo phiếu phạt'],
                    ['name' => 'Cố ý hủy hoại tài sản công ty',                 'severity' => 'extreme',  'penalty_type' => 'both',   'money_deducted' => 0,      'description' => 'Cố ý làm hỏng hoặc hủy hoại tài sản công ty. Bồi thường toàn bộ theo giá trị thực tế'],
                ],
            ],

            // ── 4. NGHIỆP VỤ BẾP ────────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Nghiệp Vụ Bếp',
                    'description' => 'Xử phạt lỗi chế biến, kiểm soát nguyên liệu và quản lý dụng cụ bếp',
                ],
                'violations' => [
                    ['name' => 'Chế biến sai công thức chuẩn',                   'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Nấu/chế biến không đúng recipe, tỷ lệ hay kỹ thuật theo tiêu chuẩn bếp'],
                    ['name' => 'Món bị trả do chất lượng chế biến kém',         'severity' => 'critical', 'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Khách từ chối nhận món do chất lượng chế biến không đạt tiêu chuẩn'],
                    ['name' => 'Trình bày món không đúng chuẩn thương hiệu',    'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Món ăn ra cho khách không đúng chuẩn trình bày và plating của thương hiệu'],
                    ['name' => 'Để thực phẩm hết hạn sử dụng trong kho',        'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Không kiểm tra và loại bỏ nguyên liệu hết hạn theo quy trình FIFO/FEFO'],
                    ['name' => 'Lãng phí nguyên liệu quá mức định lượng',       'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Sử dụng hoặc để hao hụt nguyên liệu vượt mức định lượng chuẩn không có lý do'],
                    ['name' => 'Không kiểm tra chất lượng nguyên liệu nhập',    'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Nhận nguyên liệu mà không kiểm tra chất lượng, nguồn gốc, hạn sử dụng theo quy trình'],
                    ['name' => 'Không vệ sinh dụng cụ bếp đúng quy trình',     'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Dụng cụ bếp không được vệ sinh, khử trùng đúng cách và đúng lịch theo quy trình'],
                    ['name' => 'Tái sử dụng dụng cụ chưa qua vệ sinh',         'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0,      'description' => 'Dùng lại dụng cụ bếp khi chưa được vệ sinh và khử trùng đúng chuẩn'],
                    ['name' => 'Làm hỏng dụng cụ bếp do sơ suất',              'severity' => 'high',     'penalty_type' => 'both',   'money_deducted' => 200000, 'description' => 'Dụng cụ nấu nướng bị hỏng do không tuân thủ hướng dẫn sử dụng đúng cách'],
                ],
            ],

            // ── 5. VỆ SINH & AN TOÀN ────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Vệ Sinh & An Toàn',
                    'description' => 'Xử phạt vi phạm về vệ sinh cá nhân, vệ sinh khu vực làm việc, an toàn lao động và an toàn thực phẩm',
                ],
                'violations' => [
                    ['name' => 'Vệ sinh cá nhân không đạt chuẩn',               'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Ngoại hình, quần áo, tóc tai không đảm bảo vệ sinh và chuyên nghiệp khi tiếp xúc khách'],
                    ['name' => 'Không rửa tay đúng quy trình',                   'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không rửa tay hoặc rửa tay không đúng quy trình trước khi chế biến/tiếp xúc thực phẩm'],
                    ['name' => 'Khu vực làm việc không đạt chuẩn vệ sinh',      'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Khu vực phụ trách không được dọn dẹp đúng tiêu chuẩn vệ sinh quy định'],
                    ['name' => 'Không dọn vệ sinh khu vực cuối ca',             'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không thực hiện dọn dẹp khu vực phụ trách trước khi kết thúc ca theo checklist'],
                    ['name' => 'Nhà vệ sinh không đạt chuẩn trong ca',          'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Nhà vệ sinh trong ca phụ trách không được dọn dẹp đúng lịch và tiêu chuẩn quy định'],
                    ['name' => 'Không đổ rác đúng quy định',                    'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không phân loại rác, không đổ rác đúng giờ hoặc để rác tràn ra ngoài thùng'],
                    ['name' => 'Không sử dụng thiết bị bảo hộ lao động',        'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không đeo găng tay, tạp dề, mũ hoặc thiết bị bảo hộ quy định khi làm việc'],
                    ['name' => 'Vi phạm quy tắc an toàn lao động',              'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Thực hiện hành vi gây nguy hiểm cho bản thân hoặc đồng nghiệp trong khu vực làm việc'],
                    ['name' => 'Vi phạm quy định an toàn thực phẩm',            'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không tuân thủ quy định về nhiệt độ bảo quản, thời hạn sử dụng và vệ sinh thực phẩm'],
                    ['name' => 'Phục vụ thực phẩm không đảm bảo VSATTP',        'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Phục vụ thực phẩm đã hỏng hoặc không đạt tiêu chuẩn vệ sinh an toàn thực phẩm cho khách'],
                ],
            ],

            // ── 6. VĂN HÓA & HÀNH VI ────────────────────────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Văn Hóa & Hành Vi',
                    'description' => 'Xử phạt vi phạm về thái độ làm việc, giao tiếp nội bộ, giao tiếp khách hàng, tinh thần đồng đội và bảo mật thông tin',
                ],
                'violations' => [
                    ['name' => 'Thái độ làm việc tiêu cực',                      'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Biểu hiện thái độ chán nản, lười biếng, không nhiệt tình trong công việc'],
                    ['name' => 'Không tuân thủ chỉ đạo hợp lý của quản lý',     'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Từ chối hoặc bỏ qua chỉ đạo hợp lý từ quản lý trực tiếp mà không có lý do chính đáng'],
                    ['name' => 'Tranh cãi, gây mâu thuẫn với đồng nghiệp',      'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Cãi vã, to tiếng hoặc gây bất hòa với đồng nghiệp trong môi trường làm việc'],
                    ['name' => 'Nói xấu đồng nghiệp hoặc quản lý',              'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Loan tin, nói xấu đồng nghiệp hoặc cấp trên gây ảnh hưởng đến môi trường làm việc'],
                    ['name' => 'Thái độ thiếu tôn trọng với khách hàng',        'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hành vi, lời nói hoặc cử chỉ thể hiện sự thiếu tôn trọng với khách hàng'],
                    ['name' => 'Tranh cãi trực tiếp với khách hàng',             'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Cãi vã, tranh luận hoặc có hành vi thiếu chuyên nghiệp trực tiếp với khách hàng'],
                    ['name' => 'Tự ý giảm giá hoặc miễn phí dịch vụ',          'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Áp dụng giảm giá hoặc miễn phí cho khách khi chưa được ủy quyền từ quản lý'],
                    ['name' => 'Không hỗ trợ đồng nghiệp khi được yêu cầu',    'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Từ chối hoặc phớt lờ yêu cầu hỗ trợ hợp lý từ đồng nghiệp trong giờ làm việc'],
                    ['name' => 'Tiết lộ thông tin nội bộ trái phép',            'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Chia sẻ thông tin về giá cả, quy trình, dữ liệu khách hàng hoặc nội bộ công ty ra ngoài'],
                    ['name' => 'Đăng thông tin công ty lên mạng xã hội trái phép', 'severity' => 'extreme', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Đăng thông tin, hình ảnh nội bộ hoặc nói xấu công ty trên mạng xã hội khi chưa được phép'],
                ],
            ],

            // ── 7. TRÁCH NHIỆM & HIỆU SUẤT CÔNG VIỆC ───────────────────────────
            [
                'regulation' => [
                    'name'        => 'Quy chế Trách Nhiệm & Hiệu Suất Công Việc',
                    'description' => 'Xử phạt vi phạm về KPI, hiệu suất, trách nhiệm quản lý, nghiệp vụ IT, Marketing và Kế toán',
                ],
                'violations' => [
                    // Quản lý & KPI
                    ['name' => 'Không hoàn thành KPI tháng',                     'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Kết quả làm việc không đạt chỉ tiêu KPI đã cam kết trong tháng'],
                    ['name' => 'Báo cáo sai lệch hoặc thiếu dữ liệu',           'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Nộp báo cáo công việc có thông tin sai lệch hoặc thiếu so với yêu cầu'],
                    ['name' => 'Không đào tạo nhân viên mới đúng quy trình',    'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Quản lý/TL không thực hiện onboarding và đào tạo nhân viên mới theo đúng quy trình'],
                    ['name' => 'Không xử lý khiếu nại khách hàng kịp thời',    'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Quản lý không tiếp nhận hoặc giải quyết khiếu nại của khách hàng trong thời gian quy định'],
                    // IT
                    ['name' => 'Không đạt tiến độ task IT',                      'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Task IT không hoàn thành đúng deadline đã cam kết với team/quản lý'],
                    ['name' => 'Không backup dữ liệu theo lịch định kỳ',        'severity' => 'medium',   'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Bỏ qua lịch backup dữ liệu hệ thống theo quy trình đã quy định'],
                    ['name' => 'Cấp sai quyền truy cập hệ thống',               'severity' => 'low',      'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Gán quyền hệ thống sai vai trò hoặc cấp vượt phạm vi cho phép'],
                    ['name' => 'Gây sự cố hệ thống do sơ suất',                 'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hành động sơ suất của IT gây sự cố ảnh hưởng đến hoạt động hệ thống và vận hành'],
                    // Marketing
                    ['name' => 'Đăng nội dung sai hoặc không phù hợp thương hiệu', 'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Đăng nội dung sai thông tin, vi phạm brand guideline hoặc không phù hợp với thương hiệu'],
                    ['name' => 'Lỗi chính tả hoặc ngữ pháp trong nội dung đã đăng', 'severity' => 'low',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Bài đăng có lỗi chính tả hoặc ngữ pháp rõ ràng sau khi đã publish công khai'],
                    ['name' => 'Làm mất tài sản media của dự án',                'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Làm mất file ảnh, video hoặc tài nguyên thiết kế quan trọng của dự án marketing'],
                    ['name' => 'Trễ deadline nội dung hoặc chiến dịch',          'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Không hoàn thành nội dung hoặc chiến dịch marketing đúng timeline đã cam kết'],
                    // Kế toán
                    ['name' => 'Nhập sai dữ liệu kế toán',                      'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Nhập sai số liệu vào phần mềm kế toán hoặc hệ thống quản lý tài chính'],
                    ['name' => 'Thiếu chứng từ thanh toán khi đối soát',        'severity' => 'high',     'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hóa đơn, biên lai hoặc chứng từ liên quan bị thiếu khi thực hiện đối soát'],
                    ['name' => 'Chốt lệch tiền mặt cuối ca',                    'severity' => 'critical', 'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Số tiền mặt thực tế không khớp với hệ thống khi chốt ca mà không giải trình được lý do'],
                    ['name' => 'Tự ý thay đổi thông tin nhà cung cấp',          'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Thay đổi thông tin nhà cung cấp trong hệ thống khi chưa có phê duyệt từ cấp trên'],
                    ['name' => 'Chậm nộp BHXH hoặc báo cáo thuế',              'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Nộp hồ sơ BHXH, báo cáo thuế hoặc đóng bảo hiểm trễ hạn quy định pháp luật'],
                    ['name' => 'Mất hồ sơ nhân sự hoặc tài liệu tài chính',    'severity' => 'extreme',  'penalty_type' => 'points', 'money_deducted' => 0, 'description' => 'Hồ sơ nhân viên, hợp đồng lao động hoặc tài liệu tài chính quan trọng bị thất lạc'],
                ],
            ],
        ];

        foreach ($regulations as $item) {
            $regulation = Regulation::create(array_merge($item['regulation'], ['is_active' => true]));

            foreach ($item['violations'] as $violationData) {
                Violation::create(array_merge($violationData, [
                    'regulation_id'  => $regulation->id,
                    'is_active'      => true,
                    'points_deducted' => self::POINTS_MAP[$violationData['severity']],
                ]));
            }
        }
    }
}
