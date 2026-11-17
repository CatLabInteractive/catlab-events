<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Services;

use App\Models\EmailLayout;
use App\Models\Organisation;
use Illuminate\Support\HtmlString;

/**
 * Class EmailLayouts
 *
 * The layout around every mail: the organisation's logo (or name) above a
 * white card with the mail content, and a footer with the organisation's
 * contact details and social links.
 *
 * An organisation can change the accent color and add a footer text, or
 * replace the whole layout with its own HTML. Like mail content, that HTML
 * is plain HTML with {{ placeholders }}, filled in by Placeholders::fillHtml.
 *
 * @package App\Services
 */
class EmailLayouts
{
    /**
     * The button color the mails used before the layout could be changed.
     */
    const DEFAULT_ACCENT_COLOR = '#4cb050';

    /**
     * The full HTML document of a mail: $content inside the organisation's layout.
     *
     * @param Organisation|null $organisation
     * @param string $content the mail content (trusted HTML)
     * @param EmailLayout|null $draft a (not yet saved) layout to use instead of the stored one
     * @return string
     */
    public function wrap(?Organisation $organisation, string $content, ?EmailLayout $draft = null): string
    {
        $layout = $draft ?: $this->getStoredLayout($organisation);

        $html = $layout && trim((string) $layout->custom_html) !== ''
            ? $layout->custom_html
            : $this->getDefaultHtml();

        return Placeholders::fillHtml($html, $this->getValues($organisation, $layout, $content));
    }

    /**
     * @param Organisation|null $organisation
     * @param EmailLayout|null $draft
     * @return string
     */
    public function getAccentColor(?Organisation $organisation, ?EmailLayout $draft = null): string
    {
        $layout = $draft ?: $this->getStoredLayout($organisation);

        if ($layout && !empty($layout->accent_color)) {
            return $layout->accent_color;
        }

        return self::DEFAULT_ACCENT_COLOR;
    }

    /**
     * The default layout written with placeholders: what goes out when the
     * organisation has no HTML of its own, and where an admin starts from.
     *
     * @return string
     */
    public function getDefaultHtml(): string
    {
        return file_get_contents(resource_path('views/emails/editable/layout.html'));
    }

    /**
     * @return string[] placeholder => what it is filled with
     */
    public function getPlaceholders(): array
    {
        return [
            'content' => 'De tekst van de mail (verplicht, precies één keer)',
            'layout.header' => 'Blok met het logo van de organisatie, of de naam als er geen logo is',
            'layout.footer' => 'Blok met de voettekst, de contactgegevens en de sociale media',
            'layout.accent_color' => 'Accentkleur, bv. "' . self::DEFAULT_ACCENT_COLOR . '"',
            'layout.footer_text' => 'De voettekst uit de instellingen',
            'organisation.name' => 'Naam van de organisatie',
            'organisation.logo_url' => 'Link naar het logo van de organisatie (leeg zonder logo)',
            'organisation.website_url' => 'Website van de organisatie',
            'organisation.support_email' => 'E-mailadres voor vragen',
        ];
    }

    /**
     * @param Organisation|null $organisation
     * @return EmailLayout|null
     */
    protected function getStoredLayout(?Organisation $organisation)
    {
        if (!$organisation || !$organisation->id) {
            return null;
        }

        return $organisation->emailLayout;
    }

    /**
     * @param Organisation|null $organisation
     * @param EmailLayout|null $layout
     * @param string $content
     * @return array placeholder => string (text) or HtmlString (trusted HTML)
     */
    protected function getValues(?Organisation $organisation, ?EmailLayout $layout, string $content): array
    {
        $accentColor = $this->getAccentColor($organisation, $layout);
        $footerText = $layout ? trim((string) $layout->footer_text) : '';
        $logoUrl = $organisation && $organisation->logo ? $organisation->logo->getUrl() : '';
        $name = $organisation ? (string) $organisation->name : '';

        return [
            'content' => new HtmlString($content),
            'layout.header' => new HtmlString(\View::make('emails.blocks.layoutHeader', [
                'logoUrl' => $logoUrl,
                'name' => $name,
                'accentColor' => $accentColor
            ])->render()),
            'layout.footer' => new HtmlString(\View::make('emails.blocks.layoutFooter', [
                'footerText' => $footerText,
                'lines' => array_filter([
                    $this->getContactLine($organisation),
                    $this->getSocialLine($organisation)
                ])
            ])->render()),
            'layout.accent_color' => $accentColor,
            'layout.footer_text' => $footerText,
            'organisation.name' => $name,
            'organisation.logo_url' => $logoUrl,
            'organisation.website_url' => $organisation ? (string) $organisation->website_url : '',
            'organisation.support_email' => $organisation ? (string) $organisation->support_email : '',
        ];
    }

    /**
     * Legal name, support address and website, as far as they are filled in.
     *
     * @param Organisation|null $organisation
     * @return array[] [ 'label' => string, 'url' => string|null ]
     */
    protected function getContactLine(?Organisation $organisation): array
    {
        if (!$organisation) {
            return [];
        }

        $line = [];

        if ($organisation->getLegalName()) {
            $line[] = [ 'label' => $organisation->getLegalName(), 'url' => null ];
        }

        if ($organisation->support_email) {
            $line[] = [ 'label' => $organisation->support_email, 'url' => 'mailto:' . $organisation->support_email ];
        }

        if ($organisation->website_url) {
            // "quizclub.example" reads better in a footer than "https://quizclub.example/".
            $label = rtrim(preg_replace('#^https?://#i', '', $organisation->website_url), '/');
            $line[] = [ 'label' => $label, 'url' => $organisation->website_url ];
        }

        return $line;
    }

    /**
     * Only the social networks the organisation filled in.
     *
     * @param Organisation|null $organisation
     * @return array[] [ 'label' => string, 'url' => string ]
     */
    protected function getSocialLine(?Organisation $organisation): array
    {
        if (!$organisation) {
            return [];
        }

        $line = [];
        foreach ($organisation->getSocialLinks() as $label => $url) {
            $line[] = [ 'label' => $label, 'url' => $url ];
        }

        return $line;
    }
}
