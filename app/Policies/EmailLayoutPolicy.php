<?php

namespace App\Policies;

use App\Models\EmailLayout;
use App\Models\User;

/**
 * The mail layout is set by the admins of the organisation.
 *
 * @package App\Policies
 */
class EmailLayoutPolicy
{
    public function view(User $user, EmailLayout $layout)
    {
        return $layout->organisation && $layout->organisation->isAdmin($user);
    }

    public function edit(User $user, EmailLayout $layout)
    {
        return $this->view($user, $layout);
    }
}
