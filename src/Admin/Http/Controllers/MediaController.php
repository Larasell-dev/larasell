<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\ProductImage;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var class-string<Model> $imageModel */
        $imageModel = config('larasell.models.product_image', ProductImage::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $images = $imageModel::query()
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Model $image): array => [
                'id' => $image->getKey(),
                'alt' => $image->getAttribute('alt'),
                'name' => data_get($image->getAttribute('meta'), 'original_name')
                    ?? basename($image->getAttribute('path')),
                'url' => $image->url(),
            ]);

        return Inertia::render('Media/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'mediaDeleteUrl' => route('larasell.admin.media.destroy'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'images' => $images->items(),
            'pagination' => [
                'currentPage' => $images->currentPage(),
                'from' => $images->firstItem(),
                'lastPage' => $images->lastPage(),
                'nextUrl' => $images->nextPageUrl(),
                'previousUrl' => $images->previousPageUrl(),
                'to' => $images->lastItem(),
                'total' => $images->total(),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var class-string<Model> $imageModel */
        $imageModel = config('larasell.models.product_image', ProductImage::class);
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ])['ids'];
        $images = $imageModel::query()->whereKey($ids)->get();

        if ($images->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'One or more selected images no longer exist.']);
        }

        $paths = $images->pluck('path')->all();
        DB::transaction(fn () => $images->each->delete());
        Storage::disk(config('larasell.images.disk'))->delete($paths);

        return back();
    }
}
