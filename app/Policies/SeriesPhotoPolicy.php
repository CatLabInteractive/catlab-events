<?php

namespace App\Policies;

use App\Models\Series;
use App\Models\SeriesPhoto;
use App\Models\User;

/**
 * Only the admins of the series' organisation can manage its photos.
 */
class SeriesPhotoPolicy
{
    public function index(User $user, Series $series)
    {
        return $this->isAdmin($user, $series);
    }

    public function create(User $user, Series $series)
    {
        return $this->isAdmin($user, $series);
    }

    public function view(User $user, SeriesPhoto $photo)
    {
        return $this->isAdmin($user, $photo->series);
    }

    public function edit(User $user, SeriesPhoto $photo)
    {
        return $this->isAdmin($user, $photo->series);
    }

    public function destroy(User $user, SeriesPhoto $photo)
    {
        return $this->isAdmin($user, $photo->series);
    }

    protected function isAdmin(User $user, ?Series $series)
    {
        if (!$series || !$series->organisation) {
            return false;
        }
        return $series->organisation->isAdmin($user);
    }
}
