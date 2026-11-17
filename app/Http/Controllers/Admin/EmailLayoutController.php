<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AdministersEmails;
use App\Http\Controllers\Controller;
use App\Models\EmailLayout;
use App\Services\EmailLayouts;
use App\Services\EmailTemplates;
use Illuminate\Http\Request;

/**
 * Class EmailLayoutController
 *
 * Lets organisation admins set the accent color and footer text of the mail
 * layout, or replace the whole layout with their own HTML.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailLayoutController extends Controller
{
    use AdministersEmails;

    /**
     * @var EmailLayouts
     */
    private $layouts;

    /**
     * @var EmailTemplates
     */
    private $templates;

    /**
     * @param EmailLayouts $layouts
     * @param EmailTemplates $templates
     */
    public function __construct(EmailLayouts $layouts, EmailTemplates $templates)
    {
        $this->layouts = $layouts;
        $this->templates = $templates;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit()
    {
        $organisation = $this->getAdministeredOrganisation();
        $layout = $organisation->emailLayout;

        return view('admin.emails.layout', [
            'accentColor' => $this->layouts->getAccentColor($organisation),
            'footerText' => $layout ? (string) $layout->footer_text : '',
            'customHtml' => $layout && trim((string) $layout->custom_html) !== ''
                ? $layout->custom_html
                : $this->layouts->getDefaultHtml(),
            'hasCustomHtml' => $layout && trim((string) $layout->custom_html) !== '',
            'placeholders' => $this->layouts->getPlaceholders()
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $organisation = $this->getAdministeredOrganisation();

        $this->validate($request, [
            'accent_color' => [ 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/' ],
            'footer_text' => 'nullable|string|max:2000',
            'custom_html' => 'nullable|string'
        ], [
            'accent_color.regex' => 'De accentkleur moet een kleur zijn zoals #4cb050.'
        ]);

        $customHtml = $this->getCustomHtml($request);
        if ($customHtml !== null && preg_match_all('/\{\{\s*content\s*\}\}/', $this->withoutInertBlocks($customHtml)) !== 1) {
            return redirect()->back()->withInput()->withErrors([
                'custom_html' => 'De layout moet precies één keer {{ content }} bevatten.'
            ]);
        }

        $layout = $organisation->emailLayout()->first() ?: new EmailLayout();
        $layout->accent_color = $this->blankToNull($request->input('accent_color'));
        $layout->footer_text = $this->blankToNull($request->input('footer_text'));
        $layout->custom_html = $customHtml;
        $layout->organisation()->associate($organisation);
        $layout->save();

        return redirect(action('Admin\EmailLayoutController@edit'))
            ->with('message', 'De layout is opgeslagen.');
    }

    /**
     * A copy of the HTML without the parts that never show up in the mail body:
     * comments, style blocks and the head.
     *
     * @param string $html
     * @return string
     */
    private function withoutInertBlocks($html)
    {
        return preg_replace([
            '/<!--.*?-->/s',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<head\b[^>]*>.*?<\/head>/is'
        ], '', $html);
    }

    /**
     * Go back to the default layout HTML. The accent color and footer text stay.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset()
    {
        $organisation = $this->getAdministeredOrganisation();

        $layout = $organisation->emailLayout()->first();
        if ($layout) {
            $layout->custom_html = null;
            $layout->save();
        }

        return redirect(action('Admin\EmailLayoutController@edit'))
            ->with('message', 'De standaardlayout wordt weer gebruikt.');
    }

    /**
     * A sample mail in the stored layout, or with a POST in the unsaved
     * layout from the form.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        $organisation = $this->getAdministeredOrganisation();

        $draft = null;
        if ($request->isMethod('post')) {
            $color = (string) $request->input('accent_color', '');
            $draft = new EmailLayout([
                'accent_color' => $this->blankToNull($color),
                'footer_text' => $this->blankToNull($request->input('footer_text')),
                'custom_html' => $this->getCustomHtml($request)
            ]);
        }

        $mail = $this->templates->render(
            $organisation,
            EmailTemplates::CONFIRMATION,
            $this->templates->getSampleAttributes($organisation, EmailTemplates::CONFIRMATION),
            null,
            $draft
        );

        return $this->sandboxedPreview($mail['body']);
    }

    /**
     * The submitted layout HTML. Empty, or the same as the default, is null:
     * the organisation then follows later changes to the default.
     *
     * @param Request $request
     * @return string|null
     */
    private function getCustomHtml(Request $request)
    {
        $html = (string) $request->input('custom_html', '');
        if (trim($html) === '') {
            return null;
        }

        // Browsers post line breaks as CRLF.
        $normalised = str_replace("\r\n", "\n", $html);
        if (trim($normalised) === trim($this->layouts->getDefaultHtml())) {
            return null;
        }

        return $normalised;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function blankToNull($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
