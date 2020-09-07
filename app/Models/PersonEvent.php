<?php

namespace App\Models;

/**
 * Class PersonEvent
 * @package App\Models
 */
class PersonEvent
{
    /**
     * @var
     */
    private $roles = [];

    /**
     * @var Event
     */
    private $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function addRole(string $role)
    {
        $this->roles[] = $role;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }
}