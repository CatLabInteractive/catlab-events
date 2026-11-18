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

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of the admin page form: page fields plus one translation with
 * its blocks. Only the HTTP input is checked here; the content rules (block
 * types, slug format, reserved and duplicate paths, organisation-scoped
 * parents and images) live in App\Cms\PageWriter, which the API uses too.
 *
 * Authorisation happens in the controller (organisation scoping is a 404,
 * not a 403).
 *
 * Class PageTranslationRequest
 * @package App\Http\Requests\Admin
 */
class PageTranslationRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'parent_id' => [ 'nullable', 'integer' ],
            'sort_order' => [ 'nullable', 'integer' ],
            'show_in_menu' => [ 'nullable', 'boolean' ],

            'slug' => [ 'required', 'string', 'max:191' ],
            'title' => [ 'required', 'string', 'max:255' ],
            'meta_title' => [ 'nullable', 'string', 'max:255' ],
            'meta_description' => [ 'nullable', 'string', 'max:320' ],
            'og_image_id' => [ 'nullable', 'integer' ],
            'is_published' => [ 'nullable', 'boolean' ],
            'blocks' => [ 'nullable', 'array' ],
        ];
    }

    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'slug' => 'adres (slug)',
            'title' => 'titel',
            'meta_title' => 'SEO-titel',
            'meta_description' => 'SEO-beschrijving',
            'og_image_id' => 'deelafbeelding',
            'parent_id' => 'bovenliggende pagina',
            'sort_order' => 'volgorde',
        ];
    }

    /**
     * Normalise the slug before validation: editors type "Over ons".
     */
    protected function prepareForValidation()
    {
        if (is_string($this->input('slug'))) {
            $this->merge([ 'slug' => strtolower(trim($this->input('slug'), " \t\n\r\0\x0B/")) ]);
        }
    }

    /**
     * @return array
     */
    public function pageAttributes(): array
    {
        return [
            'parent_id' => $this->input('parent_id') ?: null,
            'sort_order' => (int) $this->input('sort_order', 0),
            'show_in_menu' => $this->boolean('show_in_menu'),
        ];
    }

    /**
     * @return array
     */
    public function translationAttributes(): array
    {
        return [
            'slug' => $this->input('slug'),
            'title' => $this->input('title'),
            'meta_title' => $this->input('meta_title'),
            'meta_description' => $this->input('meta_description'),
            'og_image_id' => $this->input('og_image_id') ?: null,
            'is_published' => $this->boolean('is_published'),
            'blocks' => array_values($this->input('blocks', []) ?: []),
        ];
    }
}
