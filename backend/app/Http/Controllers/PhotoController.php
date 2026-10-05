<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves operator-uploaded deal photos from the photos disk. */
class PhotoController extends Controller
{
    public function __invoke(string $file): StreamedResponse
    {
        $disk = Storage::disk('photos');

        abort_unless(preg_match('/^[A-Za-z0-9_-]+\.(jpe?g|png|webp)$/', $file) && $disk->exists($file), 404);

        return $disk->response($file, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
