<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\EmailLayouts;
use App\Services\EmailTemplates;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvitedFromWaitingList
 *
 * An admin invited someone from the waiting list to buy a ticket: an access
 * token has been generated, the invitation mail has not gone out yet.
 *
 * @package App\Events
 */
class InvitedFromWaitingList
{
    use SerializesModels;

    /**
     * @var Event
     */
    public $event;

    /**
     * @var User
     */
    public $user;

    /**
     * The url that lets $user register with their waiting list access token.
     * @var string
     */
    public $url;

    /**
     * Subject the admin wrote, or null for the default subject.
     * @var string|null
     */
    public $subject;

    /**
     * Mail content (HTML, without the mail layout) the admin wrote, or null
     * for the default template.
     * @var string|null
     */
    public $content;

    /**
     * InvitedFromWaitingList constructor.
     * @param Event $event
     * @param User $user
     * @param string $url
     * @param string|null $subject
     * @param string|null $content
     */
    public function __construct(Event $event, User $user, string $url, ?string $subject = null, ?string $content = null)
    {
        $this->event = $event;
        $this->user = $user;
        $this->url = $url;
        $this->subject = $subject;
        $this->content = $content;
    }

    /**
     * The subject that goes out when the admin did not edit it: the event's
     * own invitation, its library template, the organisation's version, or
     * the default (EmailTemplates::resolve()).
     *
     * @param Event $event
     * @param User $user
     * @param string $url
     * @return string
     */
    public static function defaultSubject(Event $event, User $user, string $url)
    {
        return app(EmailTemplates::class)->render(
            $event->organisation,
            EmailTemplates::WAITING_LIST_INVITATION,
            self::getMailAttributes($event, $user, $url)
        )['subject'];
    }

    /**
     * The invitation text that goes out when the admin did not edit it,
     * without the surrounding mail layout, as the starting point for an
     * admin who wants to edit it.
     *
     * @param Event $event
     * @param User $user
     * @param string $url
     * @return string
     */
    public static function renderDefaultContent(Event $event, User $user, string $url)
    {
        $templates = app(EmailTemplates::class);
        $attributes = self::getMailAttributes($event, $user, $url);

        // The admin edits this text, and it goes out in the organisation's
        // layout: its button should already have the layout's accent color.
        $attributes['accentColor'] = app(EmailLayouts::class)->getAccentColor($event->organisation);

        $source = $templates->resolve($event->organisation, $event, EmailTemplates::WAITING_LIST_INVITATION);
        if ($source->hasText()) {
            return $templates->renderContent(
                $event->organisation,
                EmailTemplates::WAITING_LIST_INVITATION,
                $attributes,
                $source->content ?? ''
            );
        }

        return \View::make($source->view, $attributes)->renderSections()['content'] ?? '';
    }

    /**
     * @param Event $event
     * @param User $user
     * @param string $url
     * @return array
     */
    public static function getMailAttributes(Event $event, User $user, string $url)
    {
        return [
            'event' => $event,
            'user' => $user,
            'url' => $url
        ];
    }
}
