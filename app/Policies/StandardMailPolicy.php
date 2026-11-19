<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

/**
 * The automatic mails of an organisation are rewritten by its admins. Only
 * a rewritten mail can go back to the standard.
 *
 * @package App\Policies
 */
class StandardMailPolicy extends EmailTemplatePolicy
{
    public function destroy(User $user, EmailTemplate $template)
    {
        return $template->exists && $this->isAdmin($user, $template);
    }
}
