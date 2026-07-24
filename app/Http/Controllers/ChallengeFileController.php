<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChallengeFileController extends Controller
{
    public function download(Request $request, Challenge $challenge, ChallengeFile $file): StreamedResponse|RedirectResponse
    {
        abort_unless($file->challenge_id === $challenge->id, 404);
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        $disk = $file->disk();

        if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $file->storage_path,
                now()->addMinutes(10),
                ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($file->filename).'"'],
            ));
        }

        abort_unless($disk->exists($file->storage_path), 404);

        return $disk->download($file->storage_path, $file->filename);
    }
}
