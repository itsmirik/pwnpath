<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WriteupImageController extends Controller
{
    /**
     * Upload an inline writeup image to object storage and return its public
     * URL for embedding in the markdown (plan §5: max 2MB, jpg/png/webp).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var list<string> $mimes */
        $mimes = config('writeups.image_mimes');
        $disk = (string) config('writeups.image_disk');

        $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', $mimes),
                'max:'.(int) config('writeups.max_image_kb'),
            ],
        ]);

        $path = $request->file('image')->storePublicly(
            (string) config('writeups.image_path'),
            $disk,
        );

        abort_if($path === false, 500);

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
        ]);
    }
}
