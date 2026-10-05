<?php

namespace App\Http\View\Composers;

use App\Models\Theme;
use App\Services\Theme\ThemeResolverService;
use Carbon\Carbon;
use Illuminate\View\View;

class ThemeViewComposer
{
    public function __construct(
        protected ThemeResolverService $resolver
    ) {}

    public function compose(View $view): void
    {
        // Don't re-resolve if already provided explicitly
        if ($view->offsetExists('activeTheme')) {
            return;
        }

        $surface = 'login';
        $viewName = $view->getName();

        if (str_starts_with($viewName, 'dashboard') || str_starts_with($viewName, 'layouts.admin')) {
            $surface = 'dashboard_greeting';
        }

        // Check if admin is simulating preview in sandbox
        if (request()->has('preview_theme_id') && auth()->check() && auth()->user()->can('manage-settings')) {
            $theme = Theme::find(request()->input('preview_theme_id'));
            if ($theme) {
                $simulatedTime = request()->filled('preview_time')
                    ? Carbon::parse(request()->input('preview_time'), 'Asia/Ho_Chi_Minh')
                    : null;

                $view->with('activeTheme', $this->resolver->preview($theme, $surface, $simulatedTime));
                return;
            }
        }

        $themePayload = $this->resolver->resolve($surface, null, auth()->user());
        $view->with('activeTheme', $themePayload);
    }
}
