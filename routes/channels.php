<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('announcements', function ($user) {
    return $user->can('announcements.view');
});

Broadcast::channel('announcement.{announcementId}', function ($user, $announcementId) {
    return $user !== null;
});

Broadcast::channel('notifications', function ($user) {
    return $user !== null;
});

Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasAnyRole(['SuperAdmin', 'HR', 'Admin']);
});

Broadcast::channel('admin.reports', function (User $user): bool {
    return $user->hasRole('SuperAdmin');
});