<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Admin\Actions\DeleteMember;
use Larasell\Larasell\Admin\Models\AdminUser;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $model = $this->model();
        $currentMember = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $memberCount = $model::query()->count();

        return Inertia::render('Settings/Members/Index', [
            ...$this->layoutProps($request),
            'memberCreateUrl' => route('larasell.admin.settings.members.create'),
            'members' => $model::query()->orderBy('name')->get()->map(fn (Model $member): array => [
                'id' => $member->getKey(),
                'name' => $member->getAttribute('name'),
                'email' => $member->getAttribute('email'),
                'url' => route('larasell.admin.settings.members.show', $member->getKey()),
                'deleteUrl' => route('larasell.admin.settings.members.destroy', $member->getKey()),
                'deletable' => $memberCount > 1 && (string) $member->getKey() !== (string) $currentMember->getKey(),
                'isCurrent' => (string) $member->getKey() === (string) $currentMember->getKey(),
            ])->all(),
        ])->rootView('larasell-admin::admin');
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Settings/Members/Create', [
            ...$this->layoutProps($request),
            'membersUrl' => route('larasell.admin.settings.members.index'),
            'memberStoreUrl' => route('larasell.admin.settings.members.store'),
        ])->rootView('larasell-admin::admin');
    }

    public function show(Request $request, string $adminMember): Response
    {
        $member = $this->model()::query()->findOrFail($adminMember);

        return Inertia::render('Settings/Members/Show', [
            ...$this->layoutProps($request),
            'membersUrl' => route('larasell.admin.settings.members.index'),
            'member' => [
                'id' => $member->getKey(),
                'name' => $member->getAttribute('name'),
                'email' => $member->getAttribute('email'),
                'updateUrl' => route('larasell.admin.settings.members.update', $member->getKey()),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function store(Request $request): RedirectResponse
    {
        $model = $this->model();
        $table = (new $model)->getTable();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($table, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $model::query()->create($data);

        return redirect()->route('larasell.admin.settings.members.index');
    }

    public function update(Request $request, string $adminMember): RedirectResponse
    {
        $member = $this->model()::query()->findOrFail($adminMember);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($member->getTable(), 'email')->ignore($member->getKey(), $member->getKeyName())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        $member->fill($data)->save();

        return back();
    }

    public function destroy(Request $request, DeleteMember $deleteMember, string $adminMember): RedirectResponse
    {
        $member = $this->model()::query()->findOrFail($adminMember);
        $currentMember = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $deleteMember->handle($member, $currentMember);

        return redirect()->route('larasell.admin.settings.members.index');
    }

    /** @return class-string<AdminUser> */
    private function model(): string
    {
        return config('larasell-admin.models.admin_user', AdminUser::class);
    }

    private function layoutProps(Request $request): array
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
        ];
    }
}
