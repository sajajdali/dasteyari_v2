<?php

namespace App\Policies;

use App\Models\CaseRequest;
use App\Models\User;

class CaseRequestPolicy
{
    public function viewAny(User $u): bool { return $u->can('requests.view'); }

    public function view(User $u, CaseRequest $r): bool
    {
        return $u->can('requests.view.all')
            || $r->keepers()->where('user_id', $u->id)->exists();
    }

    public function create(User $u): bool { return $u->can('requests.create'); }
    public function update(User $u, CaseRequest $r): bool { return $u->can('requests.edit') && $this->view($u, $r); }
    public function approve(User $u): bool { return $u->can('requests.approve'); }

    /** حذف فیزیکی هرگز — بخش ۱ قاعده ۸ */
    public function delete(User $u, CaseRequest $r): bool { return false; }
}
