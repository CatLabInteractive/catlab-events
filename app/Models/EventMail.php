<?php

namespace App\Models;

use App\Services\EmailTemplates;
use App\Services\MailSource;

/**
 * One of the mails of an event, as that event sends it: its own choice (a
 * row of event_emails: a library template or an own text), and otherwise an
 * unsaved model, for a mail that follows the organisation. Saving that one
 * stores the choice; deleting it goes back to the organisation's version.
 *
 * The subject and content attributes stay the event's own text (null without
 * one, see EventEmail::hasOwnText()); getSubject() and getContent(), which
 * the api reads, are the text that goes out.
 *
 * @package App\Models
 */
class EventMail extends EventEmail
{
    protected $table = 'event_emails';

    /**
     * @param Event $event
     * @param string $type
     * @return EventMail|null null for a mail this event doesn't send and has no choice for
     */
    public static function of(Event $event, $type)
    {
        if (!is_string($type) || !in_array($type, EmailTemplates::EVENT_TYPES, true)) {
            return null;
        }

        $mail = static::query()
            ->where('event_id', '=', $event->id)
            ->where('type', '=', $type)
            ->first();

        if (!$mail) {
            if (!in_array($type, EmailTemplates::getEventTypes($event), true)) {
                return null;
            }

            $mail = new static([ 'type' => $type ]);
        }

        // The same instance, so its organisation isn't loaded per mail.
        $mail->setRelation('event', $event);
        $mail->event_id = $event->id;
        $mail->syncOriginalAttribute('event_id');

        return $mail;
    }

    /**
     * The mails the event sends, and those it still has a choice for.
     * @param Event $event
     * @return EventMail[]
     */
    public static function allOf(Event $event)
    {
        return array_values(array_filter(array_map(function ($type) use ($event) {
            return static::of($event, $type);
        }, EmailTemplates::EVENT_TYPES)));
    }

    /**
     * @return MailSource
     */
    public function getSource(): MailSource
    {
        return app(EmailTemplates::class)->resolve($this->event->organisation, $this->event, $this->type);
    }

    /**
     * The subject that goes out (with its placeholders).
     * @return string
     */
    public function getSubject()
    {
        $source = $this->getSource();

        return $source->hasText()
            ? (string) $source->subject
            : app(EmailTemplates::class)->getDefaultSubject($this->type);
    }

    /**
     * The content that goes out (with its placeholders).
     * @return string
     */
    public function getContent()
    {
        return app(EmailTemplates::class)->getStartingContent($this->event->organisation, $this->event, $this->type);
    }

    /**
     * @return bool
     */
    public function isApplicable()
    {
        return in_array($this->type, EmailTemplates::getEventTypes($this->event), true);
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
     * Where the mail comes from, in words.
     * @return string
     */
    public function getVersionAttribute()
    {
        $source = $this->getSource();

        switch ($source->kind) {
            case MailSource::EVENT:
                return 'Eigen tekst';

            case MailSource::LIBRARY:
                return 'Sjabloon: ' . $source->libraryTemplate->name;
        }

        return 'Standaard van de organisatie';
    }
}
