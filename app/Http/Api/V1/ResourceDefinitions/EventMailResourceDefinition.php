<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\EventMail;

/**
 * One of the mails of an event, identified by its type. An event follows its
 * organisation until it makes a choice: a library template
 * (email_template_id) or an own text (subject and content, with
 * email_template_id null). Deleting removes the choice.
 *
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class EventMailResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(EventMail::class);

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
            ->describe('Waar de mail vandaan komt: de organisatie, een sjabloon of een eigen tekst.')
            ->visible(true, true);

        $this->field('applicable')
            ->bool()
            ->describe('False when the event does not send this mail (any more); its choice can only be removed.')
            ->visible();

        $this->field('email_template_id')
            ->int()
            ->label('Sjabloon')
            ->describe('Kies een sjabloon, of "Eigen tekst" om het onderwerp en de inhoud hieronder te versturen. Met een sjabloon wordt de tekst hieronder niet gebruikt.')
            ->visible()
            ->writeable(false, true);

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
    }
}
