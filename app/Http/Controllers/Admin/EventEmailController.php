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
 * @package App\Http\Controllers\Admin
 */
class EventEmailController extends Controller
{
    use AdministersEmails;

    const SOURCE_ORGANISATION = 'organisation';
    const SOURCE_TEMPLATE = 'template';
    const SOURCE_OWN = 'own';

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
     * Every event mail, and where it comes from.
     *
     * @param int $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($eventId)
    {
        $event = $this->getEvent($eventId);

        $mails = [];
        foreach (EmailTemplates::EVENT_TYPES as $type) {
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
                'origin' => $origin
            ];
        }

        return view('admin.events.emails.index', [
            'event' => $event,
            'mails' => $mails
        ]);
    }

    /**
     * The choice for one mail.
     *
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $organisation = $event->organisation;

        $current = $this->templates->resolve($organisation, $event, $type);

        switch ($current->kind) {
            case MailSource::EVENT:
                $source = self::SOURCE_OWN;
                break;

            case MailSource::LIBRARY:
                $source = self::SOURCE_TEMPLATE;
                break;

            default:
                $source = self::SOURCE_ORGANISATION;
                break;
        }

        return view('admin.events.emails.edit', [
            'event' => $event,
            'type' => $type,
            'label' => $this->templates->getLabel($type),
            'source' => $source,
            'libraryTemplates' => $this->getLibraryTemplates($event, $type),
            'templateId' => $current->libraryTemplate ? $current->libraryTemplate->id : null,
            // The editor starts from the version in use.
            'subject' => $current->hasText() ? $current->subject : $this->templates->getDefaultSubject($type),
            'content' => $this->templates->getStartingContent($organisation, $event, $type),
            'placeholders' => $this->templates->getPlaceholders($type)
        ]);
    }

    /**
     * Store the choice for one mail.
     *
     * @param Request $request
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $eventId, $type)
    {
        $event = $this->getEvent($eventId);

        $this->validateChoice($request, $event, $type);

        switch ($request->input('source')) {
            case self::SOURCE_ORGANISATION:
                $event->emails()->where('type', '=', $type)->delete();
                break;

            case self::SOURCE_TEMPLATE:
                $event->emails()->updateOrCreate([ 'type' => $type ], [
                    'email_template_id' => (int) $request->input('email_template_id'),
                    'subject' => null,
                    'content' => null
                ]);
                break;

            case self::SOURCE_OWN:
                $event->emails()->updateOrCreate([ 'type' => $type ], [
                    'email_template_id' => null,
                    'subject' => (string) $request->input('subject'),
                    'content' => (string) $request->input('content')
                ]);
                break;
        }

        return redirect(action('Admin\EventEmailController@index', [ $event->id ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" is aangepast voor dit evenement.');
    }

    /**
     * The mail with the real event. A GET shows the stored choice, a POST
     * the choice from the form, without saving it.
     *
     * @param Request $request
     * @param int $eventId
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $eventId, $type)
    {
        $event = $this->getEvent($eventId);
        $organisation = $event->organisation;

        // null: what resolve() picks for the event.
        $source = null;

        if ($request->isMethod('post')) {
            switch ($request->input('source')) {
                case self::SOURCE_TEMPLATE:
                    $template = $this->getLibraryTemplates($event, $type)
                        ->firstWhere('id', (int) $request->input('email_template_id'));
                    if (!$template) {
                        abort(404);
                    }

                    $source = new MailSource(MailSource::LIBRARY, $template->subject, $template->content, null, $template);
                    break;

                case self::SOURCE_OWN:
                    $source = new MailSource(
                        MailSource::EVENT,
                        (string) $request->input('subject', ''),
                        (string) $request->input('content', '')
                    );
                    break;

                default:
                    // Without the event, resolve() gives the organisation's version.
                    $source = $this->templates->resolve($organisation, null, $type);
                    break;
            }
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
     * @param Request $request
     * @param Event $event
     * @param string $type
     */
    private function validateChoice(Request $request, Event $event, $type)
    {
        $this->validate($request, [
            'source' => [
                'required',
                Rule::in([ self::SOURCE_ORGANISATION, self::SOURCE_TEMPLATE, self::SOURCE_OWN ])
            ]
        ]);

        switch ($request->input('source')) {
            case self::SOURCE_TEMPLATE:
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
                break;

            case self::SOURCE_OWN:
                $this->validate($request, [
                    'subject' => 'required|string|max:255',
                    'content' => 'required|string'
                ]);
                break;
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
     * The event, when the user administers the event's organisation, or a 404.
     *
     * @param int $eventId
     * @return Event
     */
    private function getEvent($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        if (!$event->organisation || !$event->organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $event;
    }
}
