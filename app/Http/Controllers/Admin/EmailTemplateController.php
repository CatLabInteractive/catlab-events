<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Services\EmailTemplates;
use Illuminate\Http\Request;

/**
 * Class EmailTemplateController
 *
 * Lets organisation admins preview the transactional mails (registration,
 * cancellation, waiting list, team invitation) with made-up data, and
 * rewrite them. A rewritten mail replaces the default for every event of
 * the active organisation until it is reset.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailTemplateController extends Controller
{
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
     * Every mail, and whether the organisation rewrote it.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $organisation = $this->getAdministeredOrganisation();

        $overrides = $organisation->emailTemplates()->get()->keyBy('type');

        $types = [];
        foreach ($this->templates->getTypes() as $type => $definition) {
            $types[$type] = $definition + [
                'customised' => $overrides->has($type),
                'updated_at' => $overrides->has($type) ? $overrides->get($type)->updated_at : null
            ];
        }

        return view('admin.emails.index', [
            'types' => $types
        ]);
    }

    /**
     * The mail as it would go out now, with made-up data.
     *
     * @param string $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $this->assertType($type);

        $mail = $this->templates->render(
            $organisation,
            $type,
            $this->templates->getSampleAttributes($organisation, $type)
        );

        return view('admin.emails.show', [
            'type' => $type,
            'label' => $this->templates->getLabel($type),
            'description' => $this->templates->getTypes()[$type]['description'],
            'subject' => $mail['subject'],
            'customised' => $this->templates->getOverride($organisation, $type) !== null
        ]);
    }

    /**
     * Just the mail body, for the preview frame. With a POST, the unsaved
     * version from the edit form is rendered instead of the stored one.
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $this->assertType($type);

        $draft = null;
        if ($request->isMethod('post')) {
            $draft = new EmailTemplate([
                'type' => $type,
                'subject' => (string) $request->input('subject', ''),
                'content' => (string) $request->input('content', '')
            ]);
        }

        $mail = $this->templates->render(
            $organisation,
            $type,
            $this->templates->getSampleAttributes($organisation, $type),
            $draft
        );

        // The body is admin-written HTML: never let it run in the panel's origin.
        return response($mail['body'])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', 'sandbox');
    }

    /**
     * @param string $type
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $this->assertType($type);

        $template = $this->templates->getOverride($organisation, $type);

        return view('admin.emails.edit', [
            'type' => $type,
            'label' => $this->templates->getLabel($type),
            'subject' => $template ? $template->subject : $this->templates->getDefaultSubject($type),
            'content' => $template ? $template->content : $this->templates->getDefaultContent($type),
            'placeholders' => $this->templates->getPlaceholders($type),
            'customised' => $template !== null
        ]);
    }

    /**
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $this->assertType($type);

        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        $organisation->emailTemplates()->updateOrCreate(
            [ 'type' => $type ],
            [
                'subject' => $request->input('subject'),
                'content' => $request->input('content')
            ]
        );

        return redirect(action('Admin\EmailTemplateController@show', [ $type ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" is aangepast.');
    }

    /**
     * Throw the organisation's version away: the default goes out again.
     *
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset($type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $this->assertType($type);

        $organisation->emailTemplates()->where('type', '=', $type)->delete();

        return redirect(action('Admin\EmailTemplateController@show', [ $type ]))
            ->with('message', 'De mail "' . $this->templates->getLabel($type) . '" is terug de standaardversie.');
    }

    /**
     * The active organisation, when the user administers it. `admin` is a
     * global flag, so the organisation role is checked here too.
     *
     * @return Organisation
     */
    private function getAdministeredOrganisation()
    {
        $organisation = \Auth::user()->getActiveOrganisation();

        if (!$organisation || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $organisation;
    }

    /**
     * @param string $type
     */
    private function assertType($type)
    {
        if (!$this->templates->exists($type)) {
            abort(404);
        }
    }
}
