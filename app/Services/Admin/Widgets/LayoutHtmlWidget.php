<?php

namespace App\Services\Admin\Widgets;

use App\Services\EmailLayouts;
use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * The HTML of the mail layout, under "Geavanceerd": the organisation's own,
 * or the standard layout to start from (saving that unchanged stores
 * nothing). With the placeholders, a button that puts the standard back,
 * and the preview of the whole form.
 *
 * @package App\Services\Admin\Widgets
 */
class LayoutHtmlWidget extends BaseWidget
{
    protected $view = 'admin.emails.widgets.layout-html';

    /**
     * @var callable function (): string
     */
    private $previewUrl;

    /**
     * @param callable $previewUrl function (): string Where the preview is rendered (GET: as stored, otherwise: the form).
     */
    public function __construct(callable $previewUrl)
    {
        $this->previewUrl = $previewUrl;
    }

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $layouts = app(EmailLayouts::class);

        $stored = $field->getValue();
        $hasCustomHtml = is_string($stored) && trim($stored) !== '';

        $old = $field->old();
        $html = is_string($old) ? $old : ($hasCustomHtml ? $stored : $layouts->getDefaultHtml());

        return [
            'html' => $html,
            'defaultHtml' => $layouts->getDefaultHtml(),
            'hasCustomHtml' => $hasCustomHtml,
            'open' => $hasCustomHtml || is_string($old),
            'placeholders' => $layouts->getPlaceholders(),
            'previewAction' => call_user_func($this->previewUrl),
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

        return is_string($value) ? $this->nullIfEmpty($value) : null;
    }
}
