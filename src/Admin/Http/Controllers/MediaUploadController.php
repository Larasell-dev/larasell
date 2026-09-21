<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Larasell\Larasell\Models\ModelRegistry;

class MediaUploadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $file = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ])['image'];

        /** @var class-string<Model> $imageModel */
        $imageModel = app(ModelRegistry::class)->productImage->class();
        $imageModel::query()->create([
            'file' => $file,
            'alt' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'meta' => [
                'mime_type' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
            ],
        ]);

        return back();
    }
}
