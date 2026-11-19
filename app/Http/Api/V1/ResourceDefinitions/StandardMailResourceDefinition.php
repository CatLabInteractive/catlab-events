<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\StandardMail;

/**
 * One of the automatic mails as an organisation sends it, identified by its
 * type. Without a version of its own, subject and content are the standard
 * text; writing them makes it the organisation's version, deleting goes back
 * to the standard.
 *
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class StandardMailResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(StandardMail::class);

        $this->identifier('type')
            ->string();

        $this->field('label')
            ->string()
            ->label('Mail')
            ->visible(true, true);

        $this->field('description')
            ->string()
            ->label('Wanneer')
            ->visible(true, true);

        $this->field('version')
            ->string()
            ->label('Versie')
            ->describe('"Aangepast" wanneer de organisatie de mail herschreef, anders "Standaard".')
            ->visible(true, true);

        $this->field('customised')
            ->bool()
            ->visible();

        $this->field('subject')
            ->string()
            ->label('Onderwerp')
            ->visible()
            ->writeable(false, true);

        $this->field('content')
            ->string()
            ->label('Inhoud')
            ->describe('HTML. De {{ velden }} worden ingevuld wanneer de mail verstuurd wordt.')
            ->visible()
            ->writeable(false, true);

        $this->field('updated_at')
            ->datetime()
            ->label('Aangepast op')
            ->visible(true, true);
    }
}
