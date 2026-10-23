<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\Media;
use App\Support\MediaDeletionService;
use App\Support\MediaUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        // Unchecked boxes are absent from the payload, so read them as flags.
        $user->hide_favorites = $request->boolean('hide_favorites');
        $user->hide_resources = $request->boolean('hide_resources');

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(
        ProfileAvatarUpdateRequest $request,
        MediaUpload $mediaUpload,
    ): RedirectResponse {
        $user = $request->user();
        $previousPath = $user->avatarPath();

        $path = $mediaUpload->storeUploadedFile(
            $request->file('avatar'),
            'avatars',
            Media::diskName(),
        );

        $user->avatar = $path;
        $user->save();

        if ($previousPath !== null && $previousPath !== $path) {
            DB::afterCommit(fn (): bool => app(MediaDeletionService::class)->deleteIfUnreferenced($previousPath));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Remove the user's avatar.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $path = $user->avatarPath();

        if ($path !== null) {
            $user->avatar = null;
            $user->save();
            DB::afterCommit(fn (): bool => app(MediaDeletionService::class)->deleteIfUnreferenced($path));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar removed.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $avatarPath = $user->avatarPath();

        Auth::logout();

        $user->delete();

        if ($avatarPath !== null) {
            DB::afterCommit(fn (): bool => app(MediaDeletionService::class)->deleteIfUnreferenced($avatarPath));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
