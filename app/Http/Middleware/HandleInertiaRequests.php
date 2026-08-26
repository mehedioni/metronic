<?php

namespace App\Http\Middleware;

use App\Core\Services\SettingsService;
use App\Core\Support\Currency;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $settings = app(SettingsService::class);

        return [
            ...parent::share($request),
            // The store's name comes from config/env until an operator
            // overrides it in Settings; no component hardcodes it either way.
            'app' => [
                'name' => $settings->companyName(),
            ],
            // Store-wide settings every screen may need: the name it trades
            // under, its logo, and the currency amounts are written in.
            'settings' => $settings->forSharing(),
            // The choices Settings offers, so the form never restates them.
            'currencies' => Currency::options(),
            'auth' => [
                'user' => $user?->only('id', 'name', 'email', 'is_active'),
                'roles' => $user?->getRoleNames()->all() ?? [],
                'permissions' => $user?->permissionNames() ?? [],
            ],
            // Upload limits come from config/files.php, so a form never
            // restates them and they cannot drift from the validation rule.
            'fileLimits' => [
                'mimes' => config('files.images.mimes'),
                'maxKilobytes' => config('files.images.max_kilobytes'),
                'maxPerProduct' => config('files.images.max_per_product'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
