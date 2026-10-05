<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            [
                'name'     => 'Default TON-HR',
                'slug'     => 'default',
                'level'    => 1,
                'status'   => 'active',
                'priority' => 0,
                'start_at' => null,
                'end_at'   => null,
                'scope'    => ['login', 'app_header', 'dashboard_greeting', 'notification'],
                'config'   => [
                    'colors'   => ['accent' => '#2563EB', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => 'Chào mừng trở lại',
                        'loginSubtitle'     => 'Quản lý nhân sự, điểm thưởng và kỷ luật — tất cả tại một nơi.',
                        'dashboardGreeting' => 'Xin chào, :name 👋',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => [],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.15, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Tết Nguyên Đán',
                'slug'     => 'tet-nguyen-dan',
                'level'    => 2,
                'status'   => 'scheduled',
                'priority' => 90,
                'start_at' => '2027-01-20 00:00:00',
                'end_at'   => '2027-02-15 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting', 'notification'],
                'config'   => [
                    'colors'   => [
                        'accent'          => '#DC2626',
                        'accentSecondary' => '#F59E0B',
                        'accentContrast'  => '#FFFFFF',
                        'bgTint'          => '#FEF2F2',
                    ],
                    'content'  => [
                        'loginGreeting'     => '🧧 Chúc mừng năm mới',
                        'loginSubtitle'     => 'Kính chúc Quý nhân viên và Gia đình một năm mới An Khang Thịnh Vượng — Vạn Sự Như Ý!',
                        'dashboardGreeting' => '🧧 Chúc mừng năm mới, :name!',
                        'dashboardSubtitle' => 'Khởi đầu năm mới bứt phá mục tiêu và gặt hái nhiều thành công rực rỡ!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Xuân Bính Ngọ 2026',
                    ],
                    'visual'   => [
                        'banners'     => [
                            'dashboard_horizontal' => 'assets/images/themes/tet-dashboard-banner.jpg',
                            'login_hero_desktop'   => 'assets/images/themes/tet-login-hero.jpg',
                            'login_banner_mobile'  => 'assets/images/themes/tet-login-mobile.jpg',
                        ],
                        'decorations' => ['apricot-line-art', 'lantern-corner', 'vertical-garland'],
                        'background'  => 'tet-subtle',
                        'animation'   => ['enabled' => true, 'preset' => 'gentle-particles', 'reducedMotion' => 'static'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.20, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Quốc khánh 2/9',
                'slug'     => 'quoc-khanh-2-9',
                'level'    => 2,
                'status'   => 'active',
                'priority' => 100,
                'start_at' => '2026-08-25 00:00:00',
                'end_at'   => '2026-09-10 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting', 'notification'],
                'config'   => [
                    'colors'   => [
                        'accent'          => '#DC2626',
                        'accentSecondary' => '#F59E0B',
                        'accentContrast'  => '#FFFFFF',
                        'bgTint'          => '#FEF2F2',
                    ],
                    'content'  => [
                        'loginGreeting'     => '🇻🇳 Chào mừng Quốc khánh 2/9',
                        'loginSubtitle'     => 'Tự hào non sông Việt Nam — Đoàn kết, đổi mới và kiến tạo tương lai.',
                        'dashboardGreeting' => '🇻🇳 Chào mừng Quốc khánh 2/9, :name!',
                        'dashboardSubtitle' => 'Hòa chung khí thế non sông, phát huy tinh thần trách nhiệm và cống hiến hết mình!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Tự hào Việt Nam',
                    ],
                    'visual'   => [
                        'banners'     => [
                            'dashboard_horizontal' => 'assets/images/themes/quoc-khanh-dashboard-banner.jpg',
                            'login_hero_desktop'   => 'assets/images/themes/quoc-khanh-login-hero.jpg',
                            'login_banner_mobile'  => 'assets/images/themes/quoc-khanh-login-mobile.jpg',
                        ],
                        'decorations' => ['national-corner-ribbon', 'lotus-emblem'],
                        'background'  => 'quoc-khanh-subtle',
                        'animation'   => ['enabled' => true, 'preset' => 'gentle-particles', 'reducedMotion' => 'static'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.20, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Quốc tế Phụ nữ 8/3',
                'slug'     => 'quoc-te-phu-nu-8-3',
                'level'    => 1,
                'status'   => 'scheduled',
                'priority' => 70,
                'start_at' => '2027-03-01 00:00:00',
                'end_at'   => '2027-03-10 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting', 'notification'],
                'config'   => [
                    'colors'   => ['accent' => '#DB2777', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => '🌸 Chúc mừng ngày Quốc tế Phụ nữ 8/3',
                        'loginSubtitle'     => 'Chúc một nửa thế giới luôn rạng rỡ, hạnh phúc và thành công!',
                        'dashboardGreeting' => '🌸 Chúc mừng ngày Quốc tế Phụ nữ 8/3, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => ['delicate-floral'],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.10, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Giải phóng miền Nam 30/4',
                'slug'     => 'giai-phong-30-4',
                'level'    => 1,
                'status'   => 'scheduled',
                'priority' => 75,
                'start_at' => '2027-04-25 00:00:00',
                'end_at'   => '2027-04-30 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting'],
                'config'   => [
                    'colors'   => ['accent' => '#B91C1C', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => 'Chào mừng ngày 30/4',
                        'loginSubtitle'     => 'Kỷ niệm Ngày Giải phóng miền Nam thống nhất đất nước.',
                        'dashboardGreeting' => 'Chào mừng kỷ niệm 30/4, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => ['national-corner-ribbon'],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.10, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Quốc tế Lao động 1/5',
                'slug'     => 'quoc-te-lao-dong-1-5',
                'level'    => 1,
                'status'   => 'scheduled',
                'priority' => 72,
                'start_at' => '2027-05-01 00:00:00',
                'end_at'   => '2027-05-02 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting'],
                'config'   => [
                    'colors'   => ['accent' => '#D97706', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => 'Chúc mừng Ngày Quốc tế Lao động 1/5',
                        'loginSubtitle'     => 'Tôn vinh sự cống hiến và nhiệt huyết của mọi thành viên.',
                        'dashboardGreeting' => 'Chúc mừng Ngày Quốc tế Lao động 1/5, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => [],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.10, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Phụ nữ Việt Nam 20/10',
                'slug'     => 'phu-nu-viet-nam-20-10',
                'level'    => 1,
                'status'   => 'scheduled',
                'priority' => 70,
                'start_at' => '2026-10-15 00:00:00',
                'end_at'   => '2026-10-22 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting'],
                'config'   => [
                    'colors'   => ['accent' => '#BE185D', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => '💐 Chúc mừng Ngày Phụ nữ Việt Nam 20/10',
                        'loginSubtitle'     => 'Tri ân và chúc mừng phái đẹp TON Capital luôn xinh đẹp và tỏa sáng!',
                        'dashboardGreeting' => '💐 Chúc mừng Ngày Phụ nữ Việt Nam 20/10, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => ['delicate-floral'],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.10, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Giáng sinh / Noel',
                'slug'     => 'noel',
                'level'    => 2,
                'status'   => 'scheduled',
                'priority' => 80,
                'start_at' => '2026-12-15 00:00:00',
                'end_at'   => '2026-12-26 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting'],
                'config'   => [
                    'colors'   => ['accent' => '#059669', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => '🎄 Chúc mừng Giáng sinh',
                        'loginSubtitle'     => 'Kính chúc bạn và gia đình một mùa Giáng sinh an lành và ấm áp!',
                        'dashboardGreeting' => '🎄 Chúc mừng Giáng sinh ấm áp, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => ['pine-branch'],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.15, 'allowPrimaryCtaOverride' => false],
                ],
            ],
            [
                'name'     => 'Sinh nhật TON Capital',
                'slug'     => 'sinh-nhat-ton-capital',
                'level'    => 3,
                'status'   => 'scheduled',
                'priority' => 80,
                'start_at' => '2026-11-01 00:00:00',
                'end_at'   => '2026-11-03 23:59:59',
                'scope'    => ['login', 'app_header', 'dashboard_greeting'],
                'config'   => [
                    'colors'   => ['accent' => '#2563EB', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                    'content'  => [
                        'loginGreeting'     => '🎉 Chúc mừng sinh nhật TON Capital',
                        'loginSubtitle'     => 'Cùng chúc mừng ngày thành lập và chặng đường phát triển vững mạnh!',
                        'dashboardGreeting' => '🎉 Chúc mừng sinh nhật TON Capital, :name!',
                        'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
                    ],
                    'visual'   => [
                        'decorations' => ['festive-confetti'],
                        'background'  => 'default',
                        'animation'   => ['enabled' => false, 'preset' => 'none'],
                    ],
                    'settings' => ['maxDecorationCoverage' => 0.15, 'allowPrimaryCtaOverride' => false],
                ],
            ],
        ];

        foreach ($themes as $t) {
            Theme::updateOrCreate(['slug' => $t['slug']], $t);
        }
    }
}
