<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RewardCategory;
use App\Models\Setting;

class PolicyController extends Controller
{
    /**
     * Trang công khai "Nội quy Thưởng & Phạt" — thay thế file HTML tĩnh cũ, lấy dữ liệu
     * trực tiếp từ regulations/violations/reward_categories/reward_types nên tự động cập nhật
     * khi admin chỉnh sửa trong CRM. Không yêu cầu đăng nhập (nhân viên floor có thể không có
     * tài khoản CRM) — xem routes/web.php.
     */
    public function index()
    {
        $regulations = Regulation::with(['violations' => function ($query) {
                $query->where('is_active', true)->orderBy('id');
            }])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (Regulation $regulation) => $regulation->violations->isNotEmpty())
            ->values();

        $rewardCategories = RewardCategory::with(['rewardTypes' => function ($query) {
                $query->where('is_active', true)->orderBy('id');
            }])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (RewardCategory $category) => $category->rewardTypes->isNotEmpty())
            ->values();

        $zones = [
            'green'  => (int) Setting::getValue('greenzone_min', 90),
            'yellow' => (int) Setting::getValue('yellowzone_min', 80),
            'orange' => (int) Setting::getValue('orangezone_min', 70),
        ];

        $companyName   = Setting::getValue('company_name', 'Công ty TNHH Ton Capital');
        $defaultPoints = (int) Setting::getValue('default_score_per_month', 100);

        $violationCount = $regulations->sum(fn (Regulation $regulation) => $regulation->violations->count());
        $rewardCount    = $rewardCategories->sum(fn (RewardCategory $category) => $category->rewardTypes->count());

        return view('policy.index', compact(
            'regulations',
            'rewardCategories',
            'zones',
            'companyName',
            'defaultPoints',
            'violationCount',
            'rewardCount',
        ));
    }
}
