<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Services;

use App\Models\EmailLayout;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\EventEmail;
use App\Models\EventDate;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Class EmailTemplates
 *
 * The transactional mails an organisation can rewrite in the admin panel.
 *
 * Each mail has a default Blade template that goes out as long as neither the
 * event nor the organisation has its own version (see resolve()). An own
 * version (event text, library template, organisation version) is plain HTML
 * with {{ placeholders }}: admins never write Blade, so the values are filled
 * in here, escaped, and only the placeholders listed per type are known.
 *
 * Either way, the content goes out inside the organisation's mail layout
 * (EmailLayouts).
 *
 * @package App\Services
 */
class EmailTemplates
{
    const CONFIRMATION = 'confirmation';
    const CONFIRMATION_PLAY_LINK = 'confirmationPlayLink';
    const CANCELLATION = 'cancellation';
    const WAITING_LIST_INVITATION = 'waitingListInvitation';
    const GROUP_INVITE = 'groupInvite';

    /**
     * The mails an event can have its own version of. The team invitation
     * is not about an event.
     */
    const EVENT_TYPES = [
        self::CONFIRMATION,
        self::CONFIRMATION_PLAY_LINK,
        self::CANCELLATION,
        self::WAITING_LIST_INVITATION
    ];

    /**
     * Every mail an organisation can rewrite.
     */
    const ALL_TYPES = [ ...self::EVENT_TYPES, self::GROUP_INVITE ];

    /**
     * A route constraint (`->where('type', ...)`) that matches exactly these types.
     *
     * @param string[] $types
     * @return string
     */
    public static function routePattern(array $types = self::ALL_TYPES)
    {
        // The types are plain words, nothing to escape.
        return implode('|', $types);
    }

    /**
     * @return array[] type => [ label, description, view, subject ]
     */
    protected function definitions()
    {
        return [
            self::CONFIRMATION => [
                'label' => 'Inschrijving bevestigd',
                'description' => 'Wordt verstuurd zodra een inschrijving bevestigd is (en naar nieuwe teamleden).',
                'view' => 'emails.tickets.confirmation',
                'subject' => '{{ event.name }}: We zijn er bij!'
            ],
            self::CONFIRMATION_PLAY_LINK => [
                'label' => 'Inschrijving bevestigd (thuisquiz)',
                'description' => 'Wordt verstuurd in plaats van de gewone bevestiging wanneer de inschrijving een speellink heeft.',
                'view' => 'emails.tickets.confirmationPlayLink',
                'subject' => '{{ event.name }}: We zijn er bij!'
            ],
            self::CANCELLATION => [
                'label' => 'Inschrijving geannuleerd',
                'description' => 'Wordt verstuurd wanneer een bevestigde inschrijving geannuleerd wordt.',
                'view' => 'emails.tickets.cancellation',
                'subject' => '{{ event.name }}: We zijn er niet bij :('
            ],
            self::WAITING_LIST_INVITATION => [
                'label' => 'Uitnodiging van de wachtlijst',
                'description' => 'Wordt verstuurd wanneer een ticket vrijkomt en je iemand van de wachtlijst uitnodigt.',
                'view' => 'emails.tickets.waitingListInvitation',
                'subject' => 'Wachtlijst {{ event.name }}'
            ],
            self::GROUP_INVITE => [
                'label' => 'Uitnodiging voor een team',
                'description' => 'Wordt verstuurd wanneer een teamlid iemand uitnodigt om bij het team te komen.',
                'view' => 'emails.groups.invite',
                'subject' => 'Uitnodiging {{ team.name }}'
            ]
        ];
    }

    /**
     * @return array type => [ label, description ]
     */
    public function getTypes()
    {
        return array_map(function (array $definition) {
            return [
                'label' => $definition['label'],
                'description' => $definition['description']
            ];
        }, $this->definitions());
    }

    /**
     * @param string $type
     * @return bool
     */
    public function exists($type)
    {
        return is_string($type) && array_key_exists($type, $this->definitions());
    }

    /**
     * @param string $type
     * @return string
     */
    public function getLabel($type)
    {
        return $this->getDefinition($type)['label'];
    }

    /**
     * The organisation's own version, or null when the default goes out.
     *
     * @param Organisation|null $organisation
     * @param string $type
     * @return EmailTemplate|null
     */
    public function getOverride(?Organisation $organisation, $type)
    {
        $this->getDefinition($type);

        if (!$organisation || !$organisation->id) {
            return null;
        }

        return $organisation->emailTemplates()->organisationVersion()->where('type', '=', $type)->first();
    }

    /**
     * The version of the mail that goes out. The first match wins:
     * the event's own text, the library template picked for the event,
     * the organisation's version, the default view.
     *
     * @param Organisation|null $organisation
     * @param Event|null $event
     * @param string $type
     * @return MailSource
     */
    public function resolve(?Organisation $organisation, ?Event $event, string $type): MailSource
    {
        $definition = $this->getDefinition($type);

        // A team invitation is not about an event. An event without id is
        // made-up sample data and has no mails of its own.
        if ($type !== self::GROUP_INVITE && $event && $event->id) {
            /** @var EventEmail|null $eventEmail */
            $eventEmail = $event->emails()->where('type', '=', $type)->first();

            if ($eventEmail && $eventEmail->hasOwnText()) {
                return new MailSource(MailSource::EVENT, $eventEmail->subject, $eventEmail->content);
            }

            $libraryTemplate = $eventEmail ? $eventEmail->emailTemplate : null;
            if ($libraryTemplate) {
                return new MailSource(
                    MailSource::LIBRARY,
                    $libraryTemplate->subject,
                    $libraryTemplate->content,
                    null,
                    $libraryTemplate
                );
            }
        }

        $override = $this->getOverride($organisation, $type);
        if ($override) {
            return new MailSource(MailSource::ORGANISATION, $override->subject, $override->content);
        }

        return new MailSource(MailSource::DEFAULT, null, null, $definition['view']);
    }

    /**
     * The placeholder text of the version in use, where an admin starts
     * from when they edit the mail.
     *
     * @param Organisation|null $organisation
     * @param Event|null $event
     * @param string $type
     * @return string
     */
    public function getStartingContent(?Organisation $organisation, ?Event $event, string $type): string
    {
        $source = $this->resolve($organisation, $event, $type);

        return $source->hasText() ? ($source->content ?? '') : $this->getDefaultContent($type);
    }

    /**
     * Subject and full HTML body of the mail as it goes out: the version
     * resolve() picks for $attributes['event'] and the organisation.
     *
     * @param Organisation|null $organisation
     * @param string $type
     * @param array $attributes what the default Blade template needs
     * @param EmailTemplate|MailSource|null $template a (not yet saved) version to render instead of the
     *      stored one, or the version to render whatever the event and organisation have stored
     * @param EmailLayout|null $layout a (not yet saved) layout to render in instead of the stored one
     * @return string[] [ 'subject' => string, 'body' => string ]
     */
    public function render(
        ?Organisation $organisation,
        $type,
        array $attributes,
        EmailTemplate|MailSource|null $template = null,
        ?EmailLayout $layout = null
    ) {
        $layouts = app(EmailLayouts::class);

        // Set before any view renders, so the button block in the content
        // matches the layout around it.
        $attributes['accentColor'] = $layouts->getAccentColor($organisation, $layout);

        // A draft passed in is previewed as if it were the organisation's
        // version; otherwise the event's own mail may win (see resolve()).
        if ($template instanceof MailSource) {
            $source = $template;
        } elseif ($template) {
            $source = new MailSource(MailSource::ORGANISATION, $template->subject, $template->content);
        } else {
            $source = $this->resolve($organisation, $attributes['event'] ?? null, $type);
        }

        if (!$source->hasText()) {
            // Only the content: the Blade views still extend the old layout
            // file, which now yields nothing but this section.
            $subject = $this->renderSubject($organisation, $type, $attributes);
            $content = \View::make($source->view, $attributes)->renderSections()['content'] ?? '';
        } else {
            $subject = $this->renderSubject($organisation, $type, $attributes, $source->subject);
            $content = $this->renderContent($organisation, $type, $attributes, $source->content ?? '');
        }

        return [
            'subject' => $subject,
            'body' => $layouts->wrap($organisation, $content, $layout)
        ];
    }

    /**
     * @param Organisation|null $organisation
     * @param string $type
     * @param array $attributes
     * @param string|null $subject null for the default subject
     * @return string
     */
    public function renderSubject(?Organisation $organisation, $type, array $attributes, $subject = null)
    {
        return Placeholders::fillText(
            $subject ?? $this->getDefaultSubject($type),
            $this->getValues($organisation, $type, $attributes)
        );
    }

    /**
     * An organisation's content with its placeholders filled in (escaped),
     * without the mail layout.
     *
     * @param Organisation|null $organisation
     * @param string $type
     * @param array $attributes
     * @param string $content
     * @return string
     */
    public function renderContent(?Organisation $organisation, $type, array $attributes, $content)
    {
        return Placeholders::fillHtml($content, $this->getValues($organisation, $type, $attributes));
    }

    /**
     * @param string $type
     * @return string
     */
    public function getDefaultSubject($type)
    {
        return $this->getDefinition($type)['subject'];
    }

    /**
     * The default mail rewritten with placeholders: where an admin starts
     * from when they first edit a mail.
     *
     * @param string $type
     * @return string
     */
    public function getDefaultContent($type)
    {
        $this->getDefinition($type);

        return file_get_contents(resource_path('views/emails/editable/' . $type . '.html'));
    }

    /**
     * @param string $type
     * @return string[] placeholder => what it is filled with
     */
    public function getPlaceholders($type)
    {
        $this->getDefinition($type);

        $placeholders = [
            'organisation.name' => 'Naam van de organisatie',
        ];

        switch ($type) {
            case self::CONFIRMATION:
            case self::CONFIRMATION_PLAY_LINK:
            case self::CANCELLATION:
                $placeholders += [
                    'event.name' => 'Naam van het evenement',
                    'greeting' => '"Beste leden van <team>," of "Hallo!" zonder team',
                    'team.name' => 'Naam van het team (leeg zonder team)',
                ];
                break;

            case self::WAITING_LIST_INVITATION:
                $placeholders += [
                    'event.name' => 'Naam van het evenement',
                    'event.available_dates' => 'Data met nog vrije tickets, bv. "12/10/2026 & 19/10/2026"',
                    'user.name' => 'Naam van de uitgenodigde',
                    'invitation.url' => 'Persoonlijke link om het ticket te bestellen',
                    'invitation.button' => 'Knop "Bestel je ticket" met de persoonlijke link',
                ];
                break;

            case self::GROUP_INVITE:
                $placeholders += [
                    'team.name' => 'Naam van het team',
                    'invitation.name' => 'Naam van de uitgenodigde',
                    'inviter.name' => 'Naam van wie uitnodigt',
                    'invitation.url' => 'Link om de uitnodiging te accepteren',
                    'invitation.button' => 'Knop "Accepteren" met de link',
                ];
                break;
        }

        switch ($type) {
            case self::CONFIRMATION:
                $placeholders += [
                    'team.url' => 'Link naar de teampagina (leeg zonder team)',
                    'event.dates' => 'Blok met per datum het uur en de locatie',
                    'event.location' => 'Blok met het adres van de locatie, of de livestream',
                ];
                break;

            case self::CONFIRMATION_PLAY_LINK:
                $placeholders += [
                    'order.play_link' => 'Link om de thuisquiz te spelen',
                ];
                break;
        }

        return $placeholders;
    }

    /**
     * Data to preview a mail with. The player, team, order and links are
     * made up (unsaved). The event is made up too, unless one is passed:
     * then the real event and its first ticket category are used.
     *
     * @param Organisation $organisation
     * @param string $type
     * @param Event|null $realEvent
     * @return array
     */
    public function getSampleAttributes(Organisation $organisation, $type, ?Event $realEvent = null)
    {
        $this->getDefinition($type);

        $user = new User();
        $user->id = 0;
        $user->username = 'Voorbeeldspeler';
        $user->email = 'speler@example.com';

        $group = new Group();
        $group->id = 0;
        $group->name = 'De Voorbeeldige Quizzers';

        $venue = new Venue();
        $venue->name = 'Zaal Voorbeeld';
        $venue->address = 'Voorbeeldstraat 1';
        $venue->city = '9000 Gent';

        $date = new EventDate();
        $date->startDate = Carbon::today()->addDays(14)->setTime(20, 0);
        $date->doorsDate = Carbon::today()->addDays(14)->setTime(19, 30);
        $date->max_tickets = 100;
        $date->setRelation('ticketCategories', collect());

        $event = new Event();
        $event->name = 'Voorbeeldquiz';
        $event->setRelation('organisation', $organisation);
        $event->setRelation('venue', $venue);
        $event->setRelation('livestream', null);
        $event->setRelation('eventDates', collect([ $date ]));

        $ticketCategory = new TicketCategory();
        $ticketCategory->name = 'Standaard ticket';
        $ticketCategory->setRelation('eventDates', collect([ $date ]));

        if ($realEvent) {
            $event = $realEvent;

            $realCategory = $event->getTicketCategoriesChronologically()->first();
            if ($realCategory) {
                $ticketCategory = $realCategory;
            } else {
                $ticketCategory->setRelation('eventDates', $event->eventDates);
            }
        }

        $order = new Order();
        $order->play_link = 'https://example.com/speel/VOORBEELD';
        $order->setRelation('event', $event);
        $order->setRelation('group', $group);
        $order->setRelation('ticketCategory', $ticketCategory);

        switch ($type) {
            case self::WAITING_LIST_INVITATION:
                return [
                    'event' => $event,
                    'user' => $user,
                    'url' => action('EventController@selectTicketCategory', [ $event->id ?: 0, 'wt' => 'VOORBEELD' ])
                ];

            case self::GROUP_INVITE:
                $invitation = new GroupMember();
                $invitation->name = 'Nieuw Teamlid';
                $invitation->email = 'teamlid@example.com';

                return [
                    'from' => $user,
                    'group' => $group,
                    'invitation' => $invitation,
                    'inviteUrl' => action('GroupController@viewInvitation', [ 0, 'VOORBEELD' ])
                ];

            case self::CANCELLATION:
                return [
                    'from' => $user,
                    'event' => $event,
                    'group' => $group
                ];

            default:
                return [
                    'order' => $order,
                    'from' => $user,
                    'event' => $event,
                    'group' => $group,
                    'ticketCategory' => $ticketCategory
                ];
        }
    }

    /**
     * @param Organisation|null $organisation
     * @param string $type
     * @param array $attributes
     * @return array placeholder => string (text) or HtmlString (trusted HTML)
     */
    protected function getValues(?Organisation $organisation, $type, array $attributes)
    {
        /** @var Event|null $event */
        $event = $attributes['event'] ?? null;
        /** @var Group|null $group */
        $group = $attributes['group'] ?? null;

        $organisation = $organisation ?: ($event ? $event->organisation : null);

        $values = [
            'organisation.name' => $organisation ? $organisation->name : ''
        ];

        if ($event) {
            $values['event.name'] = $event->name;
        }

        $values['team.name'] = $group ? $group->name : '';
        $values['greeting'] = $group ? 'Beste leden van ' . $group->name . ',' : 'Hallo!';

        switch ($type) {
            case self::CONFIRMATION:
                $values['team.url'] = $group ? action('GroupController@show', [ $group->id ]) : '';
                $values['event.dates'] = new HtmlString(
                    \View::make('emails.blocks.eventDates', $attributes)->render()
                );
                $values['event.location'] = new HtmlString(
                    \View::make('emails.blocks.location', $attributes)->render()
                );
                break;

            case self::CONFIRMATION_PLAY_LINK:
                $values['order.play_link'] = $attributes['order']->play_link ?? '';
                break;

            case self::WAITING_LIST_INVITATION:
                $values['user.name'] = $attributes['user']->username;
                $values['invitation.url'] = $attributes['url'];
                $values['invitation.button'] = $this->renderButton($attributes, $attributes['url'], 'Bestel je ticket');
                $values['event.available_dates'] = $event->eventDates
                    ->filter(function ($date) { return !$date->isSoldOut(); })
                    ->map(function ($date) { return $date->startDate->format('d/m/Y'); })
                    ->join(' & ');
                break;

            case self::GROUP_INVITE:
                $values['invitation.name'] = $attributes['invitation']->name;
                $values['inviter.name'] = $attributes['from'] ? $attributes['from']->username : '';
                $values['invitation.url'] = $attributes['inviteUrl'];
                $values['invitation.button'] = $this->renderButton($attributes, $attributes['inviteUrl'], 'Accepteren');
                break;
        }

        return array_intersect_key($values, $this->getPlaceholders($type));
    }

    /**
     * @param array $attributes the mail's attributes, for the accent color
     * @param string $url
     * @param string $label
     * @return HtmlString
     */
    protected function renderButton(array $attributes, $url, $label)
    {
        return new HtmlString(\View::make('emails.blocks.button', [
            'url' => $url,
            'label' => $label,
            'accentColor' => $attributes['accentColor'] ?? null
        ])->render());
    }

    /**
     * @param string $type
     * @return array
     */
    protected function getDefinition($type)
    {
        $definitions = $this->definitions();

        if (!is_string($type) || !isset($definitions[$type])) {
            throw new InvalidArgumentException('Unknown email type: ' . $type);
        }

        return $definitions[$type];
    }
}
