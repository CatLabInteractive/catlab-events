<?php

namespace Tests\Unit;

use App\Tools\LegalDocuments;
use Tests\TestCase;

class LegalDocumentsTest extends TestCase
{
    public function testPrivacyPolicyIsInTheVisitorsLanguage()
    {
        $this->assertSame('https://accounts.catlab.eu/docs/nl/privacy', LegalDocuments::privacyUrl('nl'));
        $this->assertSame('https://accounts.catlab.eu/docs/en/privacy', LegalDocuments::privacyUrl('en'));
    }

    public function testFallsBackToDutchWhenTheDocumentIsNotTranslated()
    {
        $this->assertSame('https://accounts.catlab.eu/docs/nl/privacy', LegalDocuments::privacyUrl('fr'));
        $this->assertSame('https://accounts.catlab.eu/docs/nl/events', LegalDocuments::termsUrl('en'));
    }

    public function testUsesTheApplicationLocaleByDefault()
    {
        app()->setLocale('nl_BE.utf8');
        $this->assertSame('https://accounts.catlab.eu/docs/nl/privacy', LegalDocuments::privacyUrl());

        app()->setLocale('en');
        $this->assertSame('https://accounts.catlab.eu/docs/en/privacy', LegalDocuments::privacyUrl());
    }

    public function testBaseUrlComesFromConfig()
    {
        config(['services.catlab.docs_url' => 'https://accounts.example.test/docs/']);
        $this->assertSame('https://accounts.example.test/docs/nl/privacy', LegalDocuments::privacyUrl('nl'));
    }
}
