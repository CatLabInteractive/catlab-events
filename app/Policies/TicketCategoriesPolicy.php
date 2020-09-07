<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;

/**
 * Class TicketCategoriesPolicy
 * @package App\Policies
 */
class TicketCategoriesPolicy
{
    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function index(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function create(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function view(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function destroy(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function edit(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * Check if the event is owned by a publisher owned by this user.
     * @param User $user
     * @param Event $event
     * @return bool
     */
    protected function isAdmin(User $user, Event $event)
    {
        /** @var Organisation $organisation */
        $organisation = $event->organisation;
        if (!$organisation) {
            return false;
        }

        return $organisation->isAdmin($user);
    }
}