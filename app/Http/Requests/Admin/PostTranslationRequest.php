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

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of the admin post form: the post's date and featured image plus
 * one translation. Only the HTTP input is checked here; the content rules
 * (slug format and uniqueness, organisation-scoped image, sanitised body)
 * live in App\Cms\PostWriter, which the API uses too.
 *
 * Authorisation happens in the controller (organisation scoping is a 404,
 * not a 403).
 *
 * Class PostTranslationRequest
 * @package App\Http\Requests\Admin
 */
class PostTranslationRequest extends FormRequest
{
    /**
     * The value of <input type="datetime-local">, in the app's timezone.
     */
    const DATE_FORMAT = 'Y-m-d\TH:i';

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
            'published_at' => [ 'nullable', 'date_format:' . self::DATE_FORMAT ],
            'featured_image_id' => [ 'nullable', 'integer' ],

            'slug' => [ 'required', 'string', 'max:191' ],
            'title' => [ 'required', 'string', 'max:255' ],
            'excerpt' => [ 'nullable', 'string', 'max:1000' ],
            'author' => [ 'nullable', 'string', 'max:191' ],
            'body' => [ 'nullable', 'string' ],
            'meta_title' => [ 'nullable', 'string', 'max:255' ],
            'meta_description' => [ 'nullable', 'string', 'max:320' ],
            'is_published' => [ 'nullable', 'boolean' ],
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            'published_at.date_format' => 'De publicatiedatum is geen geldige datum en tijd.',
        ];
    }

    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'published_at' => 'publicatiedatum',
            'featured_image_id' => 'uitgelichte afbeelding',
            'slug' => 'adres (slug)',
            'title' => 'titel',
            'excerpt' => 'samenvatting',
            'author' => 'auteur',
            'body' => 'tekst',
            'meta_title' => 'SEO-titel',
            'meta_description' => 'SEO-beschrijving',
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
    public function postAttributes(): array
    {
        $publishedAt = $this->input('published_at');

        return [
            'published_at' => $publishedAt
                ? Carbon::createFromFormat(self::DATE_FORMAT, $publishedAt, config('app.timezone'))->second(0)
                : null,
            'featured_image_id' => $this->input('featured_image_id') ?: null,
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
            'excerpt' => $this->input('excerpt'),
            'author' => $this->input('author'),
            'body' => (string) $this->input('body', ''),
            'meta_title' => $this->input('meta_title'),
            'meta_description' => $this->input('meta_description'),
            'is_published' => $this->boolean('is_published'),
        ];
    }
}
