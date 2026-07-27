<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Writeup image storage disk
    |--------------------------------------------------------------------------
    |
    | Disk that inline writeup images are uploaded to. Defaults to the app's
    | default filesystem disk (s3 in prod, per .env). For local dev without
    | object-storage creds, set WRITEUP_IMAGE_DISK=public.
    |
    */

    'image_disk' => env('WRITEUP_IMAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    // Uploaded images are namespaced under this path prefix on the disk.
    'image_path' => 'writeups',

    // Max upload size in kilobytes (plan §5: 2MB).
    'max_image_kb' => 2048,

    // Allowed image mime types (plan §5: jpg / png / webp).
    'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

    // Max markdown body length, in characters.
    'max_content_chars' => 50000,

];
