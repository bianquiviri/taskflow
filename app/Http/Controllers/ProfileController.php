<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserPasswordAction;
use App\Actions\UpdateUserProfileAction;
use App\Enums\Theme;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The account of the signed in user: identity, credentials and preferences.
 * Every action works on `$request->user()`, so nobody can reach another
 * account through these routes.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'theme' => ($user->theme ?? Theme::Light)->value,
                'avatar' => $user->avatarUrl(),
                'emailVerified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    public function password(): Response
    {
        return Inertia::render('Profile/Password');
    }

    public function update(UpdateProfileRequest $request, UpdateUserProfileAction $updateUserProfile): RedirectResponse
    {
        $user = $updateUserProfile(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
        );

        $message = $user->hasVerifiedEmail()
            ? 'Profile updated.'
            : 'Profile updated. Check your inbox to confirm the new address.';

        return redirect()->route('profile.edit')->with('success', $message);
    }

    public function updatePassword(UpdatePasswordRequest $request, UpdateUserPasswordAction $updateUserPassword): RedirectResponse
    {
        $updateUserPassword($request->user(), $request->validated('password'));

        return redirect()->route('profile.password')->with('success', 'Password updated.');
    }

    public function avatar(Request $request, UserAvatarService $avatars): StreamedResponse
    {
        $path = $request->user()->avatar_path;

        abort_if($path === null, 404);

        return $avatars->stream($path);
    }

    public function storeAvatar(UpdateAvatarRequest $request, UserAvatarService $avatars): RedirectResponse
    {
        $avatars->store($request->user(), $request->file('avatar'));

        return redirect()->route('profile.edit')->with('success', 'Avatar updated.');
    }

    public function destroyAvatar(Request $request, UserAvatarService $avatars): RedirectResponse
    {
        $avatars->delete($request->user());

        return redirect()->route('profile.edit')->with('success', 'Avatar removed.');
    }
}
