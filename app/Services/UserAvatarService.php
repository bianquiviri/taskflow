<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores the avatar of a user on the private local disk.
 *
 * Files keep the extension of the uploaded mime type and live under a
 * per-user directory, so replacing an avatar is a single write plus the
 * removal of the previous file. Nothing is resized: the image is validated by
 * type and size on the way in and served exactly as it was uploaded.
 */
final readonly class UserAvatarService
{
    /**
     * The disk holding the private avatar files.
     */
    private const DISK = 'local';

    /**
     * The directory of the disk every avatar lives in.
     */
    private const DIRECTORY = 'avatars';

    /**
     * Stores the uploaded avatar and removes the previous one.
     */
    public function store(User $user, UploadedFile $avatar): string
    {
        $directory = $this->directory($user);
        $name = Str::uuid().'.'.($avatar->guessExtension() ?? 'bin');

        $this->remove($user->avatar_path);

        Storage::disk(self::DISK)->putFileAs($directory, $avatar, $name);

        $user->forceFill(['avatar_path' => $directory.'/'.$name])->save();

        return $directory.'/'.$name;
    }

    /**
     * Removes the stored avatar of a user, if there is one.
     */
    public function delete(User $user): void
    {
        $this->remove($user->avatar_path);

        $user->forceFill(['avatar_path' => null])->save();
    }

    /**
     * Streams a stored avatar file.
     */
    public function stream(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path);
    }

    private function remove(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function directory(User $user): string
    {
        return self::DIRECTORY.'/'.$user->getKey();
    }
}
