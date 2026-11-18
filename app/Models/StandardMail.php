<?php

namespace App\Models;

use App\Services\EmailTemplates;

/**
 * One of the automatic mails, as an organisation sends it: its own version
 * when it rewrote the mail (a row of email_templates without a name), and
 * otherwise an unsaved model that holds the standard text. Saving that one
 * makes it the organisation's version; deleting goes back to the standard.
 *
 * @property-read string $label
 * @property-read string $description
 * @property-read string $version
 *
 * @package App\Models
 */
class StandardMail extends EmailTemplate
{
    protected $table = 'email_templates';

    /**
     * @param Organisation $organisation
     * @param string $type
     * @return StandardMail|null null when there is no such mail
     */
    public static function of(Organisation $organisation, $type)
    {
        $templates = app(EmailTemplates::class);
        if (!is_string($type) || !$templates->exists($type)) {
            return null;
        }

        $mail = static::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('type', '=', $type)
            ->organisationVersion()
            ->first();

        if (!$mail) {
            $mail = new static([
                'type' => $type,
                'name' => '',
                'subject' => $templates->getDefaultSubject($type),
                'content' => $templates->getDefaultContent($type),
            ]);
            $mail->organisation()->associate($organisation);
        }

        return $mail;
    }

    /**
     * Every mail, in the order of the list.
     * @param Organisation $organisation
     * @return StandardMail[]
     */
    public static function allOf(Organisation $organisation)
    {
        return array_map(function ($type) use ($organisation) {
            return static::of($organisation, $type);
        }, EmailTemplates::ALL_TYPES);
    }

    /**
     * @return bool
     */
    public function isCustomised()
    {
        return $this->exists;
    }

    /**
     * @return string
     */
    public function getLabelAttribute()
    {
        return app(EmailTemplates::class)->getLabel($this->type);
    }

    /**
     * @return string
     */
    public function getDescriptionAttribute()
    {
        return app(EmailTemplates::class)->getTypes()[$this->type]['description'];
    }

    /**
     * @return string
     */
    public function getVersionAttribute()
    {
        return $this->exists ? 'Aangepast' : 'Standaard';
    }
}
