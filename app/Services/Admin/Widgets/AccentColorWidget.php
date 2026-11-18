<?php

namespace App\Services\Admin\Widgets;

use App\Services\EmailLayouts;
use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * The accent colour of the mail layout: a colour picker next to the text
 * field that is posted. Without a colour of its own, the organisation sees
 * the one its mails use now.
 *
 * @package App\Services\Admin\Widgets
 */
class AccentColorWidget extends BaseWidget
{
    protected $view = 'admin.emails.widgets.accent-color';

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $value = $field->old('', $field->getValue());
        if (!is_string($value) || $value === '') {
            $user = \Auth::user();
            $value = app(EmailLayouts::class)->getAccentColor($user ? $user->getActiveOrganisation() : null);
        }

        return [
            'value' => $value,
        ];
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string|null
     */
    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);

        return is_string($value) ? $this->nullIfEmpty(trim($value)) : null;
    }
}
