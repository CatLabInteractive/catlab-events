<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AdministersEmails;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailTemplates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Class EmailLibraryController
 *
 * Lets organisation admins keep a library of named mail templates. An event
 * can pick one of them instead of the organisation's version of a mail.
 * The group invitation is not tied to an event, so it has no templates.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailLibraryController extends Controller
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
     * Every template, and the events that use it.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $organisation = $this->getAdministeredOrganisation();

        $templates = $organisation->emailTemplates()
            ->library()
            ->with('eventEmails.event')
            ->orderBy('name')
            ->get();

        $events = [];
        foreach ($templates as $template) {
            $events[$template->id] = $this->getEventNames($template);
        }

        return view('admin.emails.library.index', [
            'templates' => $templates,
            'events' => $events,
            'labels' => $this->getTypeLabels()
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request)
    {
        $this->getAdministeredOrganisation();

        $type = $request->input('type');
        if (!$this->isLibraryType($type)) {
            $type = EmailTemplates::CONFIRMATION;
        }

        return view('admin.emails.library.create', [
            'type' => $type,
            'types' => $this->getTypeLabels(),
            'subject' => $this->templates->getDefaultSubject($type),
            'content' => $this->templates->getDefaultContent($type),
            'placeholders' => $this->templates->getPlaceholders($type)
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $organisation = $this->getAdministeredOrganisation();

        $this->validate($request, [
            'type' => [ 'required', Rule::in($this->getLibraryTypes()) ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('email_templates', 'name')
                    ->where('organisation_id', $organisation->id)
                    ->where('type', $request->input('type'))
            ],
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ], [
            'name.unique' => 'Er is al een sjabloon met deze naam voor deze mail.'
        ]);

        $organisation->emailTemplates()->create([
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'subject' => $request->input('subject'),
            'content' => $request->input('content')
        ]);

        return redirect(action('Admin\EmailLibraryController@index'))
            ->with('message', 'Het sjabloon "' . $request->input('name') . '" is aangemaakt.');
    }

    /**
     * @param int $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id)
    {
        $template = $this->getTemplate($id);

        return view('admin.emails.library.edit', [
            'template' => $template,
            'label' => $this->templates->getLabel($template->type),
            'subject' => $template->subject,
            'content' => $template->content,
            'placeholders' => $this->templates->getPlaceholders($template->type)
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $template = $this->getTemplate($id);

        $this->validate($request, [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('email_templates', 'name')
                    ->where('organisation_id', $template->organisation_id)
                    ->where('type', $template->type)
                    ->ignore($template->id)
            ],
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ], [
            'name.unique' => 'Er is al een sjabloon met deze naam voor deze mail.'
        ]);

        $template->name = $request->input('name');
        $template->subject = $request->input('subject');
        $template->content = $request->input('content');
        $template->save();

        return redirect(action('Admin\EmailLibraryController@index'))
            ->with('message', 'Het sjabloon "' . $template->name . '" is aangepast.');
    }

    /**
     * Delete a template, unless an event that still exists uses it. Rows of
     * deleted events go with it.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $template = $this->getTemplate($id)->load('eventEmails.event');

        $names = $this->getEventNames($template);
        if (count($names) > 0) {
            return redirect(action('Admin\EmailLibraryController@index'))
                ->with('error', 'Dit sjabloon wordt nog gebruikt door: ' . implode(', ', $names) . '.');
        }

        // The foreign key restricts deleting a template that is still referenced.
        $template->eventEmails()->delete();
        $template->delete();

        return redirect(action('Admin\EmailLibraryController@index'))
            ->with('message', 'Het sjabloon "' . $template->name . '" is verwijderd.');
    }

    /**
     * The stored template, or with a POST the unsaved version from the form.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $id)
    {
        $template = $this->getTemplate($id);

        if ($request->isMethod('post')) {
            $template = new EmailTemplate([
                'type' => $template->type,
                'subject' => (string) $request->input('subject', ''),
                'content' => (string) $request->input('content', '')
            ]);
        }

        return $this->renderPreview($template);
    }

    /**
     * The unsaved version of a template that does not exist yet.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function previewNew(Request $request)
    {
        $this->getAdministeredOrganisation();

        $type = $request->input('type');
        if (!$this->isLibraryType($type)) {
            abort(404);
        }

        $template = new EmailTemplate([
            'type' => $type,
            'subject' => $request->isMethod('post')
                ? (string) $request->input('subject', '')
                : $this->templates->getDefaultSubject($type),
            'content' => $request->isMethod('post')
                ? (string) $request->input('content', '')
                : $this->templates->getDefaultContent($type)
        ]);

        return $this->renderPreview($template);
    }

    /**
     * @param EmailTemplate $template Stored or unsaved.
     * @return \Illuminate\Http\Response
     */
    private function renderPreview(EmailTemplate $template)
    {
        $organisation = $this->getAdministeredOrganisation();

        $mail = $this->templates->render(
            $organisation,
            $template->type,
            $this->templates->getSampleAttributes($organisation, $template->type),
            $template
        );

        return $this->sandboxedPreview($mail['body']);
    }

    /**
     * A library template of the active organisation, or a 404.
     *
     * @param int $id
     * @return EmailTemplate
     */
    private function getTemplate($id)
    {
        return $this->getAdministeredOrganisation()
            ->emailTemplates()
            ->library()
            ->findOrFail($id);
    }

    /**
     * Names of the events that use a template, not counting deleted events.
     * Needs eventEmails.event to be loaded.
     *
     * @param EmailTemplate $template
     * @return string[] name by event id
     */
    private function getEventNames(EmailTemplate $template)
    {
        $names = [];
        foreach ($template->eventEmails as $eventEmail) {
            // A soft-deleted event does not load.
            if ($eventEmail->event) {
                $names[$eventEmail->event->id] = $eventEmail->event->name;
            }
        }

        return $names;
    }

    /**
     * The mails an event can have its own version of.
     *
     * @return string[]
     */
    private function getLibraryTypes()
    {
        return EmailTemplates::EVENT_TYPES;
    }

    /**
     * @param mixed $type
     * @return bool
     */
    private function isLibraryType($type)
    {
        return is_string($type) && in_array($type, $this->getLibraryTypes(), true);
    }

    /**
     * @return string[] Label by type
     */
    private function getTypeLabels()
    {
        $labels = [];
        foreach ($this->getLibraryTypes() as $type) {
            $labels[$type] = $this->templates->getLabel($type);
        }

        return $labels;
    }
}
