<?php

namespace App\Tools;

/**
 * Links to the legal documents (privacy policy, terms of use) hosted on
 * CatLab Accounts. The documents exist in a few languages only, so the
 * visitor's language is used when there is a version in it and Dutch
 * otherwise.
 */
class LegalDocuments
{
    public const PRIVACY = 'privacy';
    public const TERMS = 'events';

    /**
     * Languages each document is published in on CatLab Accounts
     * (templates/documents/{document}/{language}.phpt there).
     */
    private const LANGUAGES = [
        self::PRIVACY => ['nl', 'en'],
        self::TERMS => ['nl'],
    ];

    private const DEFAULT_LANGUAGE = 'nl';

    public static function privacyUrl(?string $locale = null): string
    {
        return self::url(self::PRIVACY, $locale);
    }

    public static function termsUrl(?string $locale = null): string
    {
        return self::url(self::TERMS, $locale);
    }

    public static function url(string $document, ?string $locale = null): string
    {
        $language = mb_strtolower(mb_substr($locale ?? app()->getLocale(), 0, 2));
        if (!in_array($language, self::LANGUAGES[$document] ?? [], true)) {
            $language = self::DEFAULT_LANGUAGE;
        }

        return rtrim(config('services.catlab.docs_url'), '/') . '/' . $language . '/' . $document;
    }
}
