<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Models\ThemeAuditLog;
use App\Services\Theme\ThemeResolverService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemesAdminController extends Controller
{
    public function __construct(
        protected ThemeResolverService $resolver
    ) {}

    public function index(Request $request): View
    {
        $query = Theme::query()
            ->with(['creator', 'updater']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('level')) {
            $query->where('level', (int) $request->level);
        }

        $themes = $query
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $activeLoginTheme = $this->resolver->resolve('login');
        $activeDashboardTheme = $this->resolver->resolve('dashboard_greeting');
        $conflicts = $this->resolver->detectConflicts();

        $stats = [
            'total'     => Theme::count(),
            'active'    => Theme::where('status', 'active')->count(),
            'scheduled' => Theme::where('status', 'scheduled')->count(),
            'draft'     => Theme::whereIn('status', ['draft', 'paused'])->count(),
        ];

        return view('themes.index', compact(
            'themes',
            'activeLoginTheme',
            'activeDashboardTheme',
            'conflicts',
            'stats'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                     => 'required|string|max:255',
            'slug'                     => 'required|string|max:100|alpha_dash|unique:themes,slug',
            'level'                    => 'required|integer|in:1,2,3',
            'priority'                 => 'required|integer|min:0|max:1000',
            'status'                   => 'required|string|in:draft,scheduled,active,paused',
            'timezone'                 => 'nullable|string|max:50',
            'start_at'                 => 'nullable|date',
            'end_at'                   => 'nullable|date|after_or_equal:start_at',
            'scope'                    => 'nullable|array',
            'scope.*'                  => 'string|in:login,app_header,dashboard_greeting,notification',
            'accent_color'             => ['nullable', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'bg_tint'                  => 'nullable|string',
            'login_greeting'           => 'nullable|string|max:255',
            'login_subtitle'           => 'nullable|string|max:500',
            'dashboard_greeting'       => 'nullable|string|max:255',
            'trust_line'               => 'nullable|string|max:255',
            'animation_enabled'        => 'nullable|boolean',
        ]);

        $config = [
            'colors'   => [
                'accent'         => $validated['accent_color'] ?? '#2563EB',
                'accentContrast' => '#FFFFFF',
                'bgTint'         => $validated['bg_tint'] ?? 'transparent',
            ],
            'content'  => [
                'loginGreeting'     => $validated['login_greeting'] ?? 'Chào mừng trở lại',
                'loginSubtitle'     => $validated['login_subtitle'] ?? 'Quản lý nhân sự, điểm thưởng và kỷ luật — tất cả tại một nơi.',
                'dashboardGreeting' => $validated['dashboard_greeting'] ?? 'Xin chào, :name 👋',
                'trustLine'         => $validated['trust_line'] ?? 'Hệ thống nội bộ TON Capital · Bảo mật thông tin',
            ],
            'visual'   => [
                'decorations' => $request->input('decorations', []),
                'background'  => $request->input('background', 'default'),
                'animation'   => [
                    'enabled'       => !empty($validated['animation_enabled']),
                    'preset'        => $request->input('animation_preset', 'none'),
                    'reducedMotion' => 'static',
                ],
            ],
            'settings' => [
                'maxDecorationCoverage'   => 0.15,
                'allowPrimaryCtaOverride' => false,
            ],
        ];

        $theme = Theme::create([
            'name'       => $validated['name'],
            'slug'       => $validated['slug'],
            'level'      => $validated['level'],
            'priority'   => $validated['priority'],
            'status'     => $validated['status'],
            'timezone'   => $validated['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'start_at'   => $validated['start_at'] ?? null,
            'end_at'     => $validated['end_at'] ?? null,
            'scope'      => $validated['scope'] ?? ['login', 'app_header', 'dashboard_greeting'],
            'config'     => $config,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        ThemeAuditLog::create([
            'theme_id'    => $theme->id,
            'actor_id'    => auth()->id(),
            'action'      => 'created',
            'reason'      => 'Khởi tạo chủ đề mới',
            'after'       => $theme->toArray(),
            'ip_address'  => $request->ip(),
            'occurred_at' => now(),
        ]);

        $this->resolver->clearCache();

        return redirect()->route('themes.index')->with('success', "Đã tạo chủ đề {$theme->name} thành công!");
    }

    public function update(Request $request, Theme $theme): RedirectResponse
    {
        $validated = $request->validate([
            'name'                     => 'required|string|max:255',
            'slug'                     => 'required|string|max:100|alpha_dash|unique:themes,slug,' . $theme->id,
            'level'                    => 'required|integer|in:1,2,3',
            'priority'                 => 'required|integer|min:0|max:1000',
            'status'                   => 'required|string|in:draft,scheduled,active,paused,archived',
            'timezone'                 => 'nullable|string|max:50',
            'start_at'                 => 'nullable|date',
            'end_at'                   => 'nullable|date|after_or_equal:start_at',
            'scope'                    => 'nullable|array',
            'accent_color'             => ['nullable', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'bg_tint'                  => 'nullable|string',
            'login_greeting'           => 'nullable|string|max:255',
            'login_subtitle'           => 'nullable|string|max:500',
            'dashboard_greeting'       => 'nullable|string|max:255',
            'trust_line'               => 'nullable|string|max:255',
            'animation_enabled'        => 'nullable|boolean',
        ]);

        $before = $theme->toArray();

        $config = $theme->config ?? [];
        $config['colors']['accent'] = $validated['accent_color'] ?? ($config['colors']['accent'] ?? '#2563EB');
        $config['colors']['bgTint'] = $validated['bg_tint'] ?? ($config['colors']['bgTint'] ?? 'transparent');
        $config['content']['loginGreeting'] = $validated['login_greeting'] ?? ($config['content']['loginGreeting'] ?? 'Chào mừng trở lại');
        $config['content']['loginSubtitle'] = $validated['login_subtitle'] ?? ($config['content']['loginSubtitle'] ?? '');
        $config['content']['dashboardGreeting'] = $validated['dashboard_greeting'] ?? ($config['content']['dashboardGreeting'] ?? 'Xin chào, :name 👋');
        $config['content']['trustLine'] = $validated['trust_line'] ?? ($config['content']['trustLine'] ?? '');
        $config['visual']['animation']['enabled'] = !empty($validated['animation_enabled']);

        $theme->update([
            'name'       => $validated['name'],
            'slug'       => $validated['slug'],
            'level'      => $validated['level'],
            'priority'   => $validated['priority'],
            'status'     => $validated['status'],
            'timezone'   => $validated['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'start_at'   => $validated['start_at'] ?? null,
            'end_at'     => $validated['end_at'] ?? null,
            'scope'      => $validated['scope'] ?? $theme->scope,
            'config'     => $config,
            'updated_by' => auth()->id(),
        ]);

        ThemeAuditLog::create([
            'theme_id'    => $theme->id,
            'actor_id'    => auth()->id(),
            'action'      => 'updated',
            'reason'      => 'Cập nhật thông tin chủ đề',
            'before'      => $before,
            'after'       => $theme->toArray(),
            'ip_address'  => $request->ip(),
            'occurred_at' => now(),
        ]);

        $this->resolver->clearCache();

        return redirect()->route('themes.index')->with('success', "Đã cập nhật chủ đề {$theme->name} thành công!");
    }

    public function toggleStatus(Request $request, Theme $theme): RedirectResponse
    {
        if ($theme->slug === 'default') {
            return redirect()->back()->with('error', 'Không thể thay đổi trạng thái của chủ đề mặc định.');
        }

        $before = $theme->status;
        $newStatus = match ($theme->status) {
            'active', 'scheduled' => 'paused',
            'paused', 'draft'     => $theme->start_at ? 'scheduled' : 'active',
            default               => 'active',
        };

        $theme->update([
            'status'     => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        ThemeAuditLog::create([
            'theme_id'    => $theme->id,
            'actor_id'    => auth()->id(),
            'action'      => $newStatus === 'paused' ? 'paused' : 'activated',
            'reason'      => "Chuyển trạng thái từ {$before} sang {$newStatus}",
            'before'      => ['status' => $before],
            'after'       => ['status' => $newStatus],
            'ip_address'  => $request->ip(),
            'occurred_at' => now(),
        ]);

        $this->resolver->clearCache();

        $actionText = $newStatus === 'paused' ? 'tạm dừng' : 'kích hoạt';
        return redirect()->route('themes.index')->with('success', "Đã {$actionText} chủ đề {$theme->name} thành công!");
    }

    public function rollback(Request $request, Theme $theme): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $before = $theme->status;
        $theme->update([
            'status'     => 'paused',
            'updated_by' => auth()->id(),
        ]);

        ThemeAuditLog::create([
            'theme_id'    => $theme->id,
            'actor_id'    => auth()->id(),
            'action'      => 'rollback',
            'reason'      => $request->input('reason'),
            'before'      => ['status' => $before],
            'after'       => ['status' => 'paused'],
            'ip_address'  => $request->ip(),
            'occurred_at' => now(),
        ]);

        $this->resolver->clearCache();

        return redirect()->route('themes.index')
            ->with('success', "Đã thực hiện Rollback khẩn cấp cho chủ đề {$theme->name}! Hệ thống đã trở về giao diện mặc định an toàn.");
    }

    public function duplicate(Theme $theme): RedirectResponse
    {
        $copyCount = Theme::where('slug', 'like', $theme->slug . '-copy%')->count();
        $suffix = $copyCount > 0 ? '-copy-' . ($copyCount + 1) : '-copy';

        $newTheme = $theme->replicate();
        $newTheme->name = $theme->name . ' (Bản sao)';
        $newTheme->slug = $theme->slug . $suffix;
        $newTheme->status = 'draft';
        $newTheme->created_by = auth()->id();
        $newTheme->updated_by = auth()->id();
        $newTheme->save();

        ThemeAuditLog::create([
            'theme_id'    => $newTheme->id,
            'actor_id'    => auth()->id(),
            'action'      => 'created',
            'reason'      => "Nhân bản từ chủ đề #{$theme->id} ({$theme->name})",
            'after'       => $newTheme->toArray(),
            'ip_address'  => request()->ip(),
            'occurred_at' => now(),
        ]);

        return redirect()->route('themes.index')
            ->with('success', "Đã nhân bản thành công chủ đề mới: {$newTheme->name}!");
    }

    public function destroy(Theme $theme): RedirectResponse
    {
        if ($theme->slug === 'default') {
            return redirect()->back()->with('error', 'Không thể xóa chủ đề mặc định.');
        }

        $themeName = $theme->name;
        $theme->delete();

        $this->resolver->clearCache();

        return redirect()->route('themes.index')
            ->with('success', "Đã xóa chủ đề {$themeName} thành công.");
    }

    /**
     * Preview sandbox simulation.
     */
    public function previewRender(Request $request): JsonResponse
    {
        $request->validate([
            'theme_id'       => 'required|exists:themes,id',
            'surface'        => 'nullable|string|in:login,app_header,dashboard_greeting',
            'simulated_time' => 'nullable|date',
        ]);

        $theme = Theme::findOrFail($request->input('theme_id'));
        $surface = $request->input('surface', 'login');
        $simTime = $request->filled('simulated_time')
            ? Carbon::parse($request->input('simulated_time'), 'Asia/Ho_Chi_Minh')
            : now('Asia/Ho_Chi_Minh');

        $payload = $this->resolver->preview($theme, $surface, $simTime);

        return response()->json([
            'theme'       => $payload,
            'is_active'   => $theme->isCurrentlyActive($simTime),
            'status'      => $theme->status,
            'preview_url' => route('login', ['preview_theme_id' => $theme->id, 'preview_time' => $simTime->format('Y-m-d H:i')]),
        ]);
    }

    /**
     * Public API endpoint for external/mobile clients.
     */
    public function resolveApi(Request $request): JsonResponse
    {
        $surface = $request->query('surface', 'login');
        $payload = $this->resolver->resolve($surface, null, $request->user());

        return response()->json([
            'success' => true,
            'data'    => $payload,
        ]);
    }
}
