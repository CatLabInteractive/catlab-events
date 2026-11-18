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
     * What the api refused, in its own (Dutch) words instead of the generic
     * English first line, back on the form with the input kept.
     *
     * @param \Illuminate\Http\JsonResponse $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function refusedByApi(\Illuminate\Http\JsonResponse $response)
    {
        $data = $response->getData(true);

        $lines = [];
        foreach (($data['error']['issues'] ?? []) as $errors) {
            foreach ((array) $errors as $error) {
                $lines[] = e($error);
            }
        }

        if (count($lines) === 0) {
            $lines[] = e($data['error']['message'] ?? 'Er ging iets mis.');
        }

        return redirect()->back()
            ->with('message', new \Illuminate\Support\HtmlString(implode('<br>', $lines)))
            ->withInput();
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
