<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Event;
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
 * Each mail has a default Blade template that goes out as long as the
 * organisation did not write its own version. An own version is plain HTML
 * with {{ placeholders }}: admins never write Blade, so the values are filled
 * in here, escaped, and only the placeholders listed per type are known.
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
     * Unknown placeholders are left alone, so a typo shows up in the preview.
     */
    const PLACEHOLDER_PATTERN = '/\{\{\s*([a-z_.]+)\s*\}\}/i';

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

        return $organisation->emailTemplates()->where('type', '=', $type)->first();
    }

    /**
     * Subject and full HTML body of the mail as it goes out: the
     * organisation's own version when there is one, the default otherwise.
     *
     * @param Organisation|null $organisation
     * @param string $type
     * @param array $attributes what the default Blade template needs
     * @param EmailTemplate|null $template a (not yet saved) version to render instead of the stored one
     * @return string[] [ 'subject' => string, 'body' => string ]
     */
    public function render(?Organisation $organisation, $type, array $attributes, ?EmailTemplate $template = null)
    {
        $template = $template ?: $this->getOverride($organisation, $type);

        if (!$template) {
            return [
                'subject' => $this->renderSubject($organisation, $type, $attributes),
                'body' => \View::make($this->getDefinition($type)['view'], $attributes)->render()
            ];
        }

        return [
            'subject' => $this->renderSubject($organisation, $type, $attributes, $template->subject),
            'body' => \View::make('emails.tickets.customContent', [
                'content' => $this->renderContent($organisation, $type, $attributes, $template->content)
            ])->render()
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
        $values = $this->getValues($organisation, $type, $attributes);

        return $this->fill($subject ?? $this->getDefaultSubject($type), function ($key) use ($values) {
            $value = $values[$key];
            return $value instanceof HtmlString ? trim(strip_tags($value->toHtml())) : (string) $value;
        }, array_keys($values));
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
        $values = $this->getValues($organisation, $type, $attributes);

        return $this->fill($content, function ($key) use ($values) {
            $value = $values[$key];
            return $value instanceof HtmlString ? $value->toHtml() : e($value);
        }, array_keys($values));
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
     * Made-up (unsaved) data to preview a mail with.
     *
     * @param Organisation $organisation
     * @param string $type
     * @return array
     */
    public function getSampleAttributes(Organisation $organisation, $type)
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
                    'url' => action('EventController@selectTicketCategory', [ 0, 'wt' => 'VOORBEELD' ])
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
                $values['invitation.button'] = $this->renderButton($attributes['url'], 'Bestel je ticket');
                $values['event.available_dates'] = $event->eventDates
                    ->filter(function ($date) { return !$date->isSoldOut(); })
                    ->map(function ($date) { return $date->startDate->format('d/m/Y'); })
                    ->join(' & ');
                break;

            case self::GROUP_INVITE:
                $values['invitation.name'] = $attributes['invitation']->name;
                $values['inviter.name'] = $attributes['from'] ? $attributes['from']->username : '';
                $values['invitation.url'] = $attributes['inviteUrl'];
                $values['invitation.button'] = $this->renderButton($attributes['inviteUrl'], 'Accepteren');
                break;
        }

        return array_intersect_key($values, $this->getPlaceholders($type));
    }

    /**
     * @param string $url
     * @param string $label
     * @return HtmlString
     */
    protected function renderButton($url, $label)
    {
        return new HtmlString(\View::make('emails.blocks.button', [
            'url' => $url,
            'label' => $label
        ])->render());
    }

    /**
     * @param string $template
     * @param callable $valueOf key => replacement
     * @param string[] $keys the placeholders that may be filled in
     * @return string
     */
    protected function fill($template, callable $valueOf, array $keys)
    {
        return preg_replace_callback(self::PLACEHOLDER_PATTERN, function ($matches) use ($valueOf, $keys) {
            if (!in_array($matches[1], $keys, true)) {
                return $matches[0];
            }
            return $valueOf($matches[1]);
        }, $template);
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
