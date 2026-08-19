<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Models\ProductImage;

class MediaUploadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $file = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ])['image'];
        $disk = config('larasell.images.disk');
        $path = $file->store(config('larasell.images.path'), $disk);

        try {
            /** @var class-string<Model> $imageModel */
            $imageModel = config('larasell.models.product_image', ProductImage::class);
            $imageModel::query()->create([
                'path' => $path,
                'alt' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'meta' => [
                    'mime_type' => $file->getMimeType(),
                    'original_name' => $file->getClientOriginalName(),
                ],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return back();
    }
}
