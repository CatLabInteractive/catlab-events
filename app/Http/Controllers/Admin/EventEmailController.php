<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AdministersEmails;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EmailTemplates;
use App\Services\MailSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Class EventEmailController
 *
 * Lets organisation admins choose, per event and per mail, which version goes
 * out: the organisation's version, a library template, or the event's own text.
 * Without a choice (no event_emails row) the organisation's version goes out.
 *
 * Each choice is its own action: the editor only edits the event's own text,
 * a template is picked on the overview, and "back to the organisation's
 * version" removes the event's choice.
 *
 * @package App\Http\Controllers\Admin
 */
class EventEmailController extends Controller
{
    use AdministersEmails;

    /**
     * @var EmailTemplates
     */
    private $templates;

    /**
     * @param EmailTemplates $templates
     */
    public function __construct(EmailTemplates $templates)
    {
        $this->templates = $templates;
    }

    /**
     * The mails this event sends, where each comes from, and what can be changed.
     *
     * @param int $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($eventId)
    {
        $event = $this->getEvent($eventId);
        $applicable = EmailTemplates::getEventTypes($event);
        $chosen = $event->emails()->pluck('type')->all();
        $descriptions = $this->templates->getTypes();

        $mails = [];
        foreach (EmailTemplates::EVENT_TYPES as $type) {
            $isApplicable = in_array($type, $applicable, true);
            $hasChoice = in_array($type, $chosen, true);

            // A mail this event does not send only shows up when it still has
            // a choice of its own, so that choice can be removed.
            if (!$isApplicable && !$hasChoice) {
                continue;
            }

            $source = $this->templates->resolve($event->organisation, $event, $type);

            switch ($source->kind) {
                case MailSource::EVENT:
                    $origin = 'Eigen tekst';
                    break;

                case MailSource::LIBRARY:
                    $origin = 'Sjabloon: ' . $source->libraryTemplate->name;
                    break;

                default:
                    $origin = 'Standaard van de organisatie';
                    break;
            }

            $mails[$type] = [
                'label' => $this->templates->getLabel($type),
                'description' => $descriptions[$type]['description'],
                'origin' => $origin,
                'kind' => $source->kind,
                'hasChoice' => $hasChoice,
                'isApplicable' => $isApplicable,
                'templateId' => $source->libraryTemplate ? $source->libraryTemplate->id : null,
                'libraryTemplates' => $this->getLibraryTemplates($event, $type)
            ];
        }

        return view('admin.events.emails.index', [
            'hub' => (new EventController())->getHubChrome(request(), $event->id, 'emails'),
            'event' => $event,
            'mails' => $mails
        ]);
    }

    /**
     * The editor for the event's own text. It starts from the version in use.
     *
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $this->assertManageable($event, $type);
        $organisation = $event->organisation;

        $current = $this->templates->resolve($organisation, $event, $type);

        return view('admin.events.emails.edit', [
            'event' => $event,
            'type' => $type,
            'label' => $this->templates->getLabel($type),
            'isOwnText' => $current->kind === MailSource::EVENT,
            'subject' => $current->hasText() ? $current->subject : $this->templates->getDefaultSubject($type),
            'content' => $this->templates->getStartingContent($organisation, $event, $type),
            'placeholders' => $this->templates->getPlaceholders($type)
        ]);
    }

    /**
     * Store the event's own text.
     *
     * @param Request $request
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $this->assertManageable($event, $type);

        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        $event->emails()->updateOrCreate([ 'type' => $type ], [
            'email_template_id' => null,
            'subject' => (string) $request->input('subject'),
            'content' => (string) $request->input('content')
        ]);

        return redirect(action('Admin\EventEmailController@index', [ $event->id ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" heeft nu een eigen tekst voor dit evenement.');
    }

    /**
     * Use a library template for this event. It replaces an own text.
     *
     * @param Request $request
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function template(Request $request, $eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $this->assertManageable($event, $type);

        $this->validate($request, [
            'email_template_id' => [
                'required',
                'integer',
                Rule::exists('email_templates', 'id')
                    ->where('organisation_id', $event->organisation_id)
                    ->where('type', $type)
                    ->where(function ($query) {
                        $query->where('name', '<>', '');
                    })
            ]
        ], [
            'email_template_id.required' => 'Kies een sjabloon.',
            'email_template_id.exists' => 'Dit sjabloon bestaat niet voor deze mail.'
        ]);

        $event->emails()->updateOrCreate([ 'type' => $type ], [
            'email_template_id' => (int) $request->input('email_template_id'),
            'subject' => null,
            'content' => null
        ]);

        return redirect(action('Admin\EventEmailController@index', [ $event->id ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" gebruikt nu een sjabloon voor dit evenement.');
    }

    /**
     * Remove the event's choice: the organisation's version goes out again.
     *
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset($eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $this->assertManageable($event, $type);

        $event->emails()->where('type', '=', $type)->delete();

        return redirect(action('Admin\EventEmailController@index', [ $event->id ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" gebruikt weer de standaard van de organisatie.');
    }

    /**
     * The mail with the real event. A GET shows the version in use, a POST
     * the own text from the editor, without saving it.
     *
     * @param Request $request
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $this->assertManageable($event, $type);
        $organisation = $event->organisation;

        // null: what resolve() picks for the event.
        $source = null;

        if ($request->isMethod('post')) {
            $source = new MailSource(
                MailSource::EVENT,
                (string) $request->input('subject', ''),
                (string) $request->input('content', '')
            );
        }

        $mail = $this->templates->render(
            $organisation,
            $type,
            $this->templates->getSampleAttributes($organisation, $type, $event),
            $source
        );

        return $this->sandboxedPreview($mail['body']);
    }

    /**
     * A 404 for a mail this event does not send, unless the event still has
     * a choice for it (so that choice can be seen and removed).
     *
     * @param Event $event
     * @param string $type
     */
    private function assertManageable(Event $event, $type)
    {
        if (in_array($type, EmailTemplates::getEventTypes($event), true)) {
            return;
        }

        if (!$event->emails()->where('type', '=', $type)->exists()) {
            abort(404);
        }
    }

    /**
     * The organisation's library templates of this type, by name.
     *
     * @param Event $event
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getLibraryTemplates(Event $event, $type)
    {
        return $event->organisation->emailTemplates()
            ->library()
            ->where('type', '=', $type)
            ->orderBy('name')
            ->get();
    }

    /**
     * The event, when it belongs to the active organisation and the user administers it, or a 404.
     *
     * @param int $eventId
     * @return Event
     */
    private function getEvent($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation
            || (int) $event->organisation_id !== (int) $organisation->id
            || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $event;
    }
}
