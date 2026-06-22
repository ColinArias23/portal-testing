<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'HR']);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'HR'])
            || $user->id === $report->user_id;
    }

    public function update(User $user, Report $report): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'HR']);
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->hasRole('SuperAdmin');
    }
}