<?php

namespace App\Services\Admin\Widgets;

use App\Models\EventMail;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\ProvidesOptions;
use CatLab\CharonFrontend\Widgets\SelectWidget;
use Illuminate\Http\Request;

/**
 * What an event sends for one of its mails: its own text (the subject and
 * content of the form), or one of the library templates of that mail.
 *
 * @package App\Services\Admin\Widgets
 */
class EventTemplateWidget extends SelectWidget implements ProvidesOptions
{
    /**
     * @param Request $request
     * @param FormField $field
     * @return string[]
     */
    public function getOptions(Request $request, FormField $field): array
    {
        $options = [ '' => 'Eigen tekst (het onderwerp en de inhoud hieronder)' ];

        $mail = $field->getResource() ? $field->getResource()->getSource() : null;
        if ($mail instanceof EventMail && $mail->event && $mail->event->organisation) {
            $templates = $mail->event->organisation->emailTemplates()
                ->library()
                ->where('type', '=', $mail->type)
                ->orderBy('name')
                ->get();

            foreach ($templates as $template) {
                $options[$template->id] = 'Sjabloon: ' . $template->name;
            }
        }

        return $options;
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return int|null
     */
    public function read(Request $request, FormField $field)
    {
        $value = parent::read($request, $field);

        return $value === null ? null : (int) $value;
    }
}
