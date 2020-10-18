<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Organisation;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class OrganisationResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class OrganisationResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(Organisation::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3)
        ;

        $this->field('legal_name')
            ->visible()
            ->writeable();

        $this->field('address')
            ->visible()
            ->writeable();

        $this->field('national_id')
            ->visible()
            ->writeable();

        $this->field('bank_iban')
            ->visible()
            ->writeable();

        $this->field('bank_bic')
            ->visible()
            ->writeable();

        $this->field('vat_footer')
            ->visible()
            ->writeable();

        $this->relationship('logo', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->relationship('favicon', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->field('support_email')
            ->visible()
            ->writeable();

        $this->field('blog_url')
            ->visible()
            ->writeable();

        $this->field('blog_rss_url')
            ->visible()
            ->writeable();

        $this->field('website_url')
            ->visible()
            ->writeable();

        $this->field('facebook_url')
            ->visible()
            ->writeable();

        $this->field('instagram_url')
            ->visible()
            ->writeable();

        $this->field('googleplus_url')
            ->visible()
            ->writeable();

        $this->field('linkedin_url')
            ->visible()
            ->writeable();

        $this->field('twitter_url')
            ->visible()
            ->writeable();

        $this->field('helpdesk_url')
            ->visible()
            ->writeable();

        $this->field('footer_html')
            ->visible()
            ->writeable();

        $this->field('chatwoot_url')
            ->visible()
            ->writeable();

        $this->field('chatwoot_token')
            ->visible()
            ->writeable();

        $this->field('chatwoot_livestream_token')
            ->visible()
            ->writeable();

        $this->field('livestream_css')
            ->visible()
            ->writeable();

        $this->field('uitpas')
            ->bool()
            ->visible()
            ->writeable();

        $this->field('rocketchat_url')
            ->string()
            ->visible()
            ->writeable();

        $this->field('rocketchat_admin_username')
            ->string()
            ->visible()
            ->writeable();

        $this->field('rocketchat_admin_password')
            ->string()
            ->visible()
            ->writeable();
    }
}
