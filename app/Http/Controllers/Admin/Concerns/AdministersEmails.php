<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Organisation;

/**
 * Trait AdministersEmails
 *
 * Shared by the controllers of the E-mails settings: finding the
 * organisation the user administers, and answering with a safe preview.
 *
 * @package App\Http\Controllers\Admin\Concerns
 */
trait AdministersEmails
{
    /**
     * The active organisation, when the user administers it. `admin` is a
     * global flag, so the organisation role is checked here too.
     *
     * @return Organisation
     */
    protected function getAdministeredOrganisation()
    {
        $organisation = \Auth::user()->getActiveOrganisation();

        if (!$organisation || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $organisation;
    }

    /**
     * A mail body as the response of a preview frame.
     *
     * @param string $body
     * @return \Illuminate\Http\Response
     */
    protected function sandboxedPreview($body)
    {
        // The body is admin-written HTML: never let it run in the panel's origin.
        return response($body)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', 'sandbox');
    }

    /**
     * The row buttons of a list of mails say what they do: "Aanpassen" and
     * "Terug naar standaard" instead of a pencil and a bin.
     *
     * @param string $action
     * @param \CatLab\CharonFrontend\Models\Table\ResourceAction $rowAction
     * @return \CatLab\CharonFrontend\Models\Table\ResourceAction
     */
    protected function labelledMailAction(string $action, $rowAction)
    {
        if ($action === \CatLab\Charon\Enums\Action::EDIT) {
            $rowAction->setIcon(null)->showLabel()->setStyle('outline-primary');
        } elseif ($action === \CatLab\Charon\Enums\Action::DESTROY) {
            $rowAction->setIcon(null)->showLabel()->setStyle('outline-danger');
        }

        return $rowAction;
    }

    /**
     * A mail field of a posted Charon form (fields[subject]).
     *
     * @param \Illuminate\Http\Request $request
     * @param string $name
     * @return string
     */
    protected function draftField(\Illuminate\Http\Request $request, $name)
    {
        $value = $request->input('fields.' . $name, '');
        return is_string($value) ? $value : '';
    }
}
