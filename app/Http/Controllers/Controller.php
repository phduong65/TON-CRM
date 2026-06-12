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
            ],
            'Khen thưởng' => [
                'view-rewards'           => 'Xem phiếu thưởng',
                'create-rewards'         => 'Tạo phiếu thưởng',
                'delete-rewards'         => 'Xóa phiếu thưởng',
                'approve-rewards'        => 'Duyệt phiếu thưởng',
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
            'Hệ thống' => [
                'view-activity-log' => 'Xem nhật ký hoạt động',
                'manage-settings'   => 'Cài đặt hệ thống',
                'manage-users'      => 'Quản lý người dùng',
                'manage-roles'      => 'Quản lý vai trò & quyền hạn',
            ],
        ];
    }
}
