<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\ProductImage;

class MediaController extends Controller
{
    use ResolvesAdminUser;

    public function index(Request $request): Response
    {
        $imageModel = app(ModelRegistry::class)->productImage->class();
        $admin = $this->adminUser($request);

        $images = $imageModel::query()
            ->latest()
            ->latest($imageModel::query()->getModel()->getKeyName())
            ->paginate(24)
            ->withQueryString()
            ->through(fn (ProductImage $image): array => [
                'id' => $image->getKey(),
                'alt' => $image->getAttribute('alt'),
                'name' => data_get($image->getAttribute('meta'), 'original_name')
                    ?? basename($image->getAttribute('path')),
                'url' => $image->url(),
            ]);

        return Inertia::render('Media/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'mediaDeleteUrl' => route('larasell.admin.media.destroy'),
            'mediaUploadUrl' => route('larasell.admin.media.uploads.store'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
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
        $imageModel = app(ModelRegistry::class)->productImage->class();
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
