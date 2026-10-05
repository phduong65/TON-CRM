<?php

namespace App\Services\Theme;

use App\Models\Theme;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ThemeResolverService
{
    public const CACHE_PREFIX = 'ton_hr_resolved_theme_';
    public const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Default enterprise fallback theme.
     */
    public function getDefaultThemePayload(): array
    {
        return [
            'id'             => null,
            'name'           => 'Default TON-HR',
            'slug'           => 'default',
            'level'          => 1,
            'level_label'    => 'Corporate',
            'priority'       => 0,
            'is_default'     => true,
            'colors'         => [
                'accent'         => '#2563EB',
                'accentContrast' => '#FFFFFF',
                'bgTint'         => 'transparent',
            ],
            'content'        => [
                'loginGreeting'     => 'Chào mừng trở lại',
                'loginSubtitle'     => 'Quản lý nhân sự, điểm thưởng và kỷ luật — tất cả tại một nơi.',
                'dashboardGreeting' => 'Xin chào, :name 👋',
                'trustLine'         => 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
            ],
            'visual'         => [
                'decorations' => [],
                'background'  => 'default',
                'animation'   => [
                    'enabled'       => false,
                    'preset'        => 'none',
                    'reducedMotion' => 'static',
                ],
            ],
            'settings'       => [
                'maxDecorationCoverage'   => 0.15,
                'allowPrimaryCtaOverride' => false,
            ],
            'resolved_at'    => now()->toIso8601String(),
            'valid_until'    => null,
        ];
    }

    /**
     * Resolve the winning theme for a given surface and time.
     */
    public function resolve(string $surface = 'login', ?Carbon $atTime = null, ?User $user = null): array
    {
        // If testing custom atTime, bypass cache
        if ($atTime !== null) {
            return $this->doResolve($surface, $atTime, $user);
        }

        $cacheKey = self::CACHE_PREFIX . $surface;
        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($surface, $user) {
            return $this->doResolve($surface, now('Asia/Ho_Chi_Minh'), $user);
        });
    }

    /**
     * Actual resolution logic without cache.
     */
    protected function doResolve(string $surface, Carbon $now, ?User $user = null): array
    {
        $nowStr = $now->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        // Query candidate themes
        $candidates = Theme::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->where(function ($q) use ($nowStr) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $nowStr);
            })
            ->where(function ($q) use ($nowStr) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $nowStr);
            })
            ->where(function ($q) use ($surface) {
                $q->whereNull('scope')
                  ->orWhereJsonContains('scope', $surface);
            })
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->get();

        if ($candidates->isEmpty()) {
            $default = $this->getDefaultThemePayload();
            $default['valid_until'] = $this->findNextBoundaryTime($surface, $now);
            return $default;
        }

        /** @var Theme $winner */
        $winner = $candidates->first();

        return $this->formatThemePayload($winner, $now, $this->findNextBoundaryTime($surface, $now));
    }

    /**
     * Sandbox preview for a theme (no cache, includes drafts).
     */
    public function preview(Theme $theme, string $surface = 'login', ?Carbon $simulatedTime = null): array
    {
        $now = $simulatedTime ?? now('Asia/Ho_Chi_Minh');
        $payload = $this->formatThemePayload($theme, $now, null);
        $payload['is_preview'] = true;
        return $payload;
    }

    /**
     * Format a Theme model into a clean, safe payload for Blade or API.
     */
    public function formatThemePayload(Theme $theme, Carbon $now, ?string $validUntil = null): array
    {
        $config = $theme->config ?? [];
        $defaults = $this->getDefaultThemePayload();

        $colors = array_merge($defaults['colors'], $config['colors'] ?? []);
        $content = array_merge($defaults['content'], $config['content'] ?? []);
        $visual = array_merge($defaults['visual'], $config['visual'] ?? []);
        $settings = array_merge($defaults['settings'], $config['settings'] ?? []);

        return [
            'id'             => $theme->id,
            'name'           => $theme->name,
            'slug'           => $theme->slug,
            'level'          => (int) $theme->level,
            'level_label'    => $theme->getLevelLabel(),
            'priority'       => (int) $theme->priority,
            'is_default'     => false,
            'colors'         => $colors,
            'content'        => $content,
            'visual'         => $visual,
            'settings'       => $settings,
            'scope'          => $theme->scope ?? ['login', 'app_header', 'dashboard_greeting'],
            'resolved_at'    => $now->toIso8601String(),
            'valid_until'    => $validUntil,
        ];
    }

    /**
     * Find next upcoming start_at or end_at to invalidate cache smartly.
     */
    protected function findNextBoundaryTime(string $surface, Carbon $now): ?string
    {
        $nowStr = $now->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        $nextStart = Theme::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->where('start_at', '>', $nowStr)
            ->where(function ($q) use ($surface) {
                $q->whereNull('scope')->orWhereJsonContains('scope', $surface);
            })
            ->min('start_at');

        $nextEnd = Theme::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->where('end_at', '>', $nowStr)
            ->where(function ($q) use ($surface) {
                $q->whereNull('scope')->orWhereJsonContains('scope', $surface);
            })
            ->min('end_at');

        $times = array_filter([$nextStart, $nextEnd]);
        if (empty($times)) {
            return null;
        }

        sort($times);
        return Carbon::parse($times[0])->toIso8601String();
    }

    /**
     * Detect conflicts between active/scheduled themes.
     */
    public function detectConflicts(): array
    {
        $themes = Theme::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->orderBy('start_at')
            ->orderByDesc('priority')
            ->get();

        $conflicts = [];
        $count = $themes->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $themes[$i];
                $b = $themes[$j];

                // Check time overlap
                if ($this->isDateOverlap($a->start_at, $a->end_at, $b->start_at, $b->end_at)) {
                    $winner = $a->priority >= $b->priority ? $a : $b;
                    $loser = $winner->id === $a->id ? $b : $a;

                    $conflicts[] = [
                        'theme_a'     => ['id' => $a->id, 'name' => $a->name, 'priority' => $a->priority],
                        'theme_b'     => ['id' => $b->id, 'name' => $b->name, 'priority' => $b->priority],
                        'winner_id'   => $winner->id,
                        'winner_name' => $winner->name,
                        'loser_id'    => $loser->id,
                        'loser_name'  => $loser->name,
                        'reason'      => $a->priority === $b->priority
                            ? 'Cùng độ ưu tiên — ưu tiên theme cập nhật sau'
                            : "Độ ưu tiên cao hơn ({$winner->priority} > {$loser->priority})",
                    ];
                }
            }
        }

        return $conflicts;
    }

    protected function isDateOverlap(?Carbon $startA, ?Carbon $endA, ?Carbon $startB, ?Carbon $endB): bool
    {
        if (!$startA || !$endA || !$startB || !$endB) {
            return false;
        }

        return $startA->lte($endB) && $endA->gte($startB);
    }

    /**
     * Invalidate all resolved theme caches.
     */
    public function clearCache(): void
    {
        $surfaces = ['login', 'app_header', 'dashboard_greeting', 'notification'];
        foreach ($surfaces as $surface) {
            Cache::forget(self::CACHE_PREFIX . $surface);
        }
    }
}
