<?php

namespace App\Http\Controllers;

use App\Core\Services\SettingsService;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Settings, as two separate concerns: the store, which is a permission, and
 * the signed-in user's own profile, which is not.
 *
 * Both return to where they were submitted from, because they are saved from a
 * drawer over whichever page the user was on.
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->settings->set([
            SettingsService::COMPANY_NAME => $request->string('company_name')->toString(),
            SettingsService::CURRENCY => $request->string('currency')->upper()->toString(),
        ]);

        if ($request->hasFile('logo')) {
            $this->settings->putLogo($request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $this->settings->clearLogo();
        }

        return back()->with('success', 'Settings saved.');
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Profile updated.');
    }
}
