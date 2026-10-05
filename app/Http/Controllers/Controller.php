<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function permissionGroups(): array
    {
        return [
            'Nhân viên' => [
                'view-employees'   => 'Xem danh sách',
                'create-employees' => 'Thêm mới',
                'edit-employees'   => 'Chỉnh sửa',
                'delete-employees' => 'Xóa',
            ],
            'Đội nhóm' => [
                'view-teams'   => 'Xem danh sách',
                'create-teams' => 'Thêm mới',
                'edit-teams'   => 'Chỉnh sửa',
                'delete-teams' => 'Xóa',
            ],
            'Chi nhánh' => [
                'view-branches'   => 'Xem danh sách',
                'create-branches' => 'Thêm mới',
                'edit-branches'   => 'Chỉnh sửa',
                'delete-branches' => 'Xóa',
            ],
            'Vi phạm' => [
                'view-violations'   => 'Xem danh sách',
                'create-violations' => 'Thêm mới',
                'edit-violations'   => 'Chỉnh sửa',
                'delete-violations' => 'Xóa',
            ],
            'Xử phạt' => [
                'view-penalties'    => 'Xem phiếu phạt',
                'create-penalties'  => 'Tạo phiếu phạt',
                'approve-penalties' => 'Duyệt phiếu phạt',
                'revoke-penalties'  => 'Thu hồi phiếu phạt',
                'delete-penalties'  => 'Xóa phiếu phạt',
            ],
            'Khen thưởng' => [
                'view-rewards'           => 'Xem phiếu thưởng',
                'create-rewards'         => 'Tạo phiếu thưởng',
                'delete-rewards'         => 'Xóa phiếu thưởng',
                'approve-rewards'        => 'Duyệt phiếu thưởng',
                'revoke-rewards'         => 'Thu hồi phiếu thưởng',
                'view-reward-types'      => 'Xem loại thưởng',
                'create-reward-types'    => 'Thêm loại thưởng',
                'edit-reward-types'      => 'Sửa loại thưởng',
                'delete-reward-types'    => 'Xóa loại thưởng',
                'view-reward-categories' => 'Xem danh mục thưởng',
                'create-reward-categories' => 'Thêm danh mục thưởng',
                'edit-reward-categories'   => 'Sửa danh mục thưởng',
                'delete-reward-categories' => 'Xóa danh mục thưởng',
            ],
            'Báo cáo chéo' => [
                'view-reports'    => 'Xem báo cáo',
                'create-reports'  => 'Tạo báo cáo',
                'approve-reports' => 'Duyệt báo cáo',
            ],
            'Quy chế' => [
                'view-regulations'   => 'Xem danh sách',
                'create-regulations' => 'Thêm mới',
                'edit-regulations'   => 'Chỉnh sửa',
                'delete-regulations' => 'Xóa',
            ],
            'Khiếu nại' => [
                'create-appeals' => 'Tạo khiếu nại',
                'view-appeals'   => 'Xem khiếu nại',
                'review-appeals' => 'Xử lý khiếu nại',
            ],
            'Ca làm việc' => [
                'view-shifts'   => 'Xem mẫu ca',
                'create-shifts' => 'Thêm mẫu ca',
                'edit-shifts'   => 'Chỉnh sửa',
                'delete-shifts' => 'Xóa',
            ],
            'Xếp ca' => [
                'view-shift-schedules'   => 'Xem lịch xếp ca',
                'create-shift-schedules' => 'Xếp ca',
                'edit-shift-schedules'   => 'Chỉnh sửa lịch',
                'delete-shift-schedules' => 'Xóa lịch',
                'view-own-schedule'      => 'Xem lịch của bản thân',
            ],
            'Điểm chấm công' => [
                'view-attendance-locations'   => 'Xem điểm chấm công',
                'create-attendance-locations' => 'Thêm điểm chấm công',
                'edit-attendance-locations'   => 'Chỉnh sửa',
                'delete-attendance-locations' => 'Xóa',
            ],
            'Chấm công' => [
                'view-attendance'      => 'Xem báo cáo chấm công',
                'checkin-attendance'   => 'Tự chấm công',
                'view-own-attendance'  => 'Xem lịch sử chấm công của bản thân',
                'import-attendance'    => 'Nhập chấm công (import)',
                'create-attendance-logs' => 'Chấm công hộ',
                'edit-attendance-logs'   => 'Sửa lượt chấm công',
                'delete-attendance-logs' => 'Xóa lượt chấm công',
            ],
            'Xác nhận công' => [
                'view-own-timesheet-confirmation' => 'Tự xem/xác nhận công của bản thân',
                'view-timesheet-confirmations'    => 'Xem xác nhận công của nhân viên khác',
                'confirm-timesheet-on-behalf'     => 'Xác nhận công hộ',
            ],
            'Nghỉ phép & Đổi ca' => [
                'view-leave-requests'    => 'Xem đơn nghỉ phép',
                'create-leave-requests'  => 'Tạo đơn nghỉ phép',
                'approve-leave-requests' => 'Duyệt đơn nghỉ phép',
                'delete-leave-requests'  => 'Xóa đơn nghỉ phép',
                'view-shift-swaps'       => 'Xem đơn đổi ca',
                'create-shift-swaps'     => 'Tạo đơn đổi ca',
                'approve-shift-swaps'    => 'Duyệt đơn đổi ca',
                'delete-shift-swaps'     => 'Xóa đơn đổi ca',
            ],
            'Yêu cầu khác' => [
                'view-staff-requests'    => 'Xem yêu cầu',
                'create-staff-requests'  => 'Tạo yêu cầu',
                'edit-staff-requests'    => 'Sửa yêu cầu',
                'approve-staff-requests' => 'Duyệt yêu cầu',
                'delete-staff-requests'  => 'Xóa yêu cầu',
            ],
            'Phép năm' => [
                'view-annual-leave' => 'Xem báo cáo phép năm',
            ],
            'Ngày nghỉ lễ' => [
                'view-holidays'   => 'Xem danh sách',
                'create-holidays' => 'Thêm mới',
                'edit-holidays'   => 'Chỉnh sửa',
                'delete-holidays' => 'Xóa',
            ],
            'Xuất báo cáo Excel' => [
                'export-shift-schedules' => 'Xuất lịch xếp ca',
                'export-attendance'      => 'Xuất báo cáo chấm công',
                'export-own-schedule'    => 'Xuất lịch cá nhân',
            ],
            'Hệ thống' => [
                'view-activity-log'  => 'Xem nhật ký hoạt động',
                'manage-settings'    => 'Cài đặt hệ thống',
                'manage-users'       => 'Quản lý người dùng',
                'manage-roles'       => 'Quản lý vai trò & quyền hạn',
                'view-notifications' => 'Xem thông báo',
                'create-notifications' => 'Tạo thông báo',
                'view-log-viewer'    => 'Xem log hệ thống',
                'impersonate-users'  => 'Đăng nhập giả danh (impersonate)',
            ],
        ];
    }
}
