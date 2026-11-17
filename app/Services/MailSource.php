<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Services;

use App\Models\EmailTemplate;

/**
 * Class MailSource
 *
 * The version of a mail that goes out, as EmailTemplates::resolve() picked
 * it. Every kind except DEFAULT holds a subject and content with
 * {{ placeholders }}; DEFAULT holds the Blade view of the default mail.
 *
 * @package App\Services
 */
class MailSource
{
    /** The event's own text. */
    const EVENT = 'event';

    /** A library template picked for the event. */
    const LIBRARY = 'library';

    /** The organisation's own version. */
    const ORGANISATION = 'organisation';

    /** The default Blade view. */
    const DEFAULT = 'default';

    /**
     * @param string $kind one of the constants above
     * @param string|null $subject null for DEFAULT
     * @param string|null $content null for DEFAULT
     * @param string|null $view only for DEFAULT
     * @param EmailTemplate|null $libraryTemplate only for LIBRARY
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $subject = null,
        public readonly ?string $content = null,
        public readonly ?string $view = null,
        public readonly ?EmailTemplate $libraryTemplate = null
    ) {
    }

    /**
     * @return bool whether this version has its own subject and content
     */
    public function hasText(): bool
    {
        return $this->kind !== self::DEFAULT;
    }
}
