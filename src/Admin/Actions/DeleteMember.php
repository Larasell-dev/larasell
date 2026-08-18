<?php

namespace Larasell\Larasell\Admin\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteMember
{
    public function handle(Model $member, Model $currentMember): void
    {
        DB::transaction(function () use ($member, $currentMember): void {
            $members = $member->newQuery()->lockForUpdate()->get();

            if ($members->count() <= 1) {
                throw ValidationException::withMessages([
                    'member' => 'At least one admin member must remain.',
                ]);
            }

            if ((string) $member->getKey() === (string) $currentMember->getKey()) {
                throw ValidationException::withMessages([
                    'member' => 'You cannot delete your own admin account.',
                ]);
            }

            $members->firstWhere($member->getKeyName(), $member->getKey())?->delete();
        });
    }
}
