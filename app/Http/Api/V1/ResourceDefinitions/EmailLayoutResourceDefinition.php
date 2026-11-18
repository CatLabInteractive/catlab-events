<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\EmailLayout;

/**
 * The layout every mail of an organisation goes out in. Each field is null
 * while the organisation follows the standard.
 *
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class EmailLayoutResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(EmailLayout::class);

        $this->identifier('organisation_id')
            ->int();

        $this->field('accent_color')
            ->string()
            ->label('Accentkleur')
            ->describe('Een kleur zoals #4cb050.')
            ->visible(true, true)
            ->writeable(false, true);

        $this->field('footer_text')
            ->string()
            ->label('Voettekst')
            ->describe('Staat boven de contactgegevens van de organisatie.')
            ->visible(true, true)
            ->writeable(false, true);

        $this->field('custom_html')
            ->string()
            ->label('HTML van de layout')
            ->describe('Moet precies één keer {{ content }} bevatten. Leeg of gelijk aan de standaardlayout: de organisatie volgt de standaard.')
            ->visible(true, true)
            ->writeable(false, true);
    }
}
