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

namespace App\Cms;

use App\Http\Controllers\SitemapController;
use App\Models\Organisation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Rules\OrganisationAsset;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for blog posts and their translations. The admin
 * editor and the Charon API both go through here, so validation (slug
 * format, one slug per organisation and locale, same-organisation featured
 * image), body sanitising and cache busting are identical everywhere.
 *
 * Errors are thrown as Illuminate ValidationExceptions keyed by field; the
 * admin turns them into a redirect with errors, the API into a 422.
 *
 * Class PostWriter
 * @package App\Cms
 */
class PostWriter
{
    /**
     * Fields of a translation that can be written.
     */
    const TRANSLATION_FIELDS = [
        'slug', 'title', 'excerpt', 'author', 'body', 'meta_title', 'meta_description', 'is_published',
    ];

    /**
     * Fields of a post that can be written.
     */
    const POST_FIELDS = [ 'published_at', 'featured_image_id' ];

    /**
     * @var HtmlSanitizer
     */
    private $sanitizer;

    /**
     * @var Blog
     */
    private $blog;

    /**
     * @param HtmlSanitizer $sanitizer
     * @param Blog $blog
     */
    public function __construct(HtmlSanitizer $sanitizer, Blog $blog)
    {
        $this->sanitizer = $sanitizer;
        $this->blog = $blog;
    }

    /**
     * Create a post and its first translation.
     * @param Organisation $organisation
     * @param array $postAttributes see POST_FIELDS
     * @param string $locale
     * @param array $translationAttributes see TRANSLATION_FIELDS
     * @return PostTranslation
     * @throws ValidationException
     */
    public function createPost(
        Organisation $organisation,
        array $postAttributes,
        string $locale,
        array $translationAttributes
    ): PostTranslation {
        return $this->transaction($organisation, function () use ($organisation, $postAttributes, $locale, $translationAttributes) {
            $post = new Post();
            $post->organisation()->associate($organisation);
            $this->fillPost($post, $postAttributes);
            $this->preparePost($post);
            $post->save();

            $translation = new PostTranslation();
            $translation->post()->associate($post);
            $translation->locale = $locale;
            $this->fillTranslation($translation, $translationAttributes);
            $this->prepareTranslation($translation);
            $translation->save();

            $post->unsetRelation('translations');

            return $translation;
        });
    }

    /**
     * Update the post's own fields (date, featured image).
     * @param Post $post
     * @param array $attributes
     * @return Post
     * @throws ValidationException
     */
    public function updatePost(Post $post, array $attributes): Post
    {
        $this->fillPost($post, $attributes);

        return $this->savePost($post);
    }

    /**
     * Validate and save a post whose fields are already set on the model
     * (new or existing; the API fills the model through Charon first).
     * @param Post $post
     * @return Post
     * @throws ValidationException
     */
    public function savePost(Post $post): Post
    {
        return $this->transaction($post->organisation, function () use ($post) {
            $this->preparePost($post);
            $post->save();

            return $post;
        });
    }

    /**
     * Create or update the translation of $post in $locale.
     * @param Post $post
     * @param string $locale
     * @param array $attributes see TRANSLATION_FIELDS; missing keys keep their value
     * @return PostTranslation
     * @throws ValidationException
     */
    public function saveTranslation(Post $post, string $locale, array $attributes): PostTranslation
    {
        return $this->transaction($post->organisation, function () use ($post, $locale, $attributes) {
            $translation = $post->translations()->where('locale', '=', $locale)->first();
            if (!$translation) {
                $translation = new PostTranslation();
                $translation->locale = $locale;
            }
            $translation->setRelation('post', $post);
            $translation->post_id = $post->id;

            $this->fillTranslation($translation, $attributes);
            $this->prepareTranslation($translation);
            $translation->save();

            $post->unsetRelation('translations');

            return $translation;
        });
    }

    /**
     * Validate and save a translation whose fields are already set on the
     * model (the API fills it through Charon first). The post must be set.
     * @param PostTranslation $translation
     * @return PostTranslation
     * @throws ValidationException
     */
    public function saveFilledTranslation(PostTranslation $translation): PostTranslation
    {
        $post = $translation->post;

        return $this->transaction($post ? $post->organisation : null, function () use ($translation, $post) {
            $this->prepareTranslation($translation);
            $translation->save();

            if ($post) {
                $post->unsetRelation('translations');
            }

            return $translation;
        });
    }

    /**
     * @param PostTranslation $translation
     * @throws ValidationException
     */
    public function deleteTranslation(PostTranslation $translation): void
    {
        $post = $translation->post;

        $this->transaction($post ? $post->organisation : null, function () use ($translation, $post) {
            $translation->delete();
            if ($post) {
                $post->unsetRelation('translations');
            }
        });
    }

    /**
     * Delete a post and its translations.
     * @param Post $post
     * @throws ValidationException
     */
    public function deletePost(Post $post): void
    {
        $this->transaction($post->organisation, function () use ($post) {
            // Translations go for real: they hold the (unique) slug.
            foreach ($post->translations()->get() as $translation) {
                $translation->delete();
            }
            $post->delete();
        });
    }

    /**
     * Validate and normalise the post's own fields as they are set on the model.
     * @param Post $post
     * @throws ValidationException
     */
    public function preparePost(Post $post): void
    {
        $organisation = $post->organisation;
        if (!$organisation) {
            throw ValidationException::withMessages([ 'organisation' => 'Een bericht hoort bij een organisatie.' ]);
        }

        // Read the raw value: an unparseable date from the API arrives as false.
        $publishedAt = $post->getAttributes()['published_at'] ?? null;

        Validator::make([
            'published_at' => $publishedAt instanceof DateTimeInterface ? $publishedAt->format('Y-m-d H:i:s') : $publishedAt,
            'featured_image_id' => $post->featured_image_id,
        ], [
            'published_at' => [ 'nullable', 'date' ],
            'featured_image_id' => [ 'nullable', 'integer', new OrganisationAsset($organisation->id) ],
        ], [
            'published_at.date' => 'De publicatiedatum is geen geldige datum.',
        ], [
            'published_at' => 'publicatiedatum',
            'featured_image_id' => 'uitgelichte afbeelding',
        ])->validate();

        $post->featured_image_id = $post->featured_image_id ? (int) $post->featured_image_id : null;
        if ($publishedAt === '' || $publishedAt === null) {
            $post->published_at = null;
        }
    }

    /**
     * Validate and normalise a translation as it is set on the model: field
     * rules, a free slug, a sanitised body. Leaves the model ready to save.
     * @param PostTranslation $translation
     * @throws ValidationException
     */
    public function prepareTranslation(PostTranslation $translation): void
    {
        /** @var Post $post */
        $post = $translation->post;
        $organisation = $post ? $post->organisation : null;
        if (!$post || !$organisation) {
            throw ValidationException::withMessages([ 'post' => 'Een vertaling hoort bij een bericht.' ]);
        }

        $data = [
            'locale' => $translation->locale,
            'slug' => $translation->slug,
            'title' => $translation->title,
            'excerpt' => $translation->excerpt,
            'author' => $translation->author,
            'body' => $translation->body,
            'meta_title' => $translation->meta_title,
            'meta_description' => $translation->meta_description,
            'is_published' => $translation->is_published,
        ];

        Validator::make($data, [
            'locale' => [ 'required', 'string', 'in:' . implode(',', config('cms.locales')) ],
            'slug' => [ 'required', 'string', 'max:191', 'regex:/^[a-z0-9\-]+$/' ],
            'title' => [ 'required', 'string', 'max:255' ],
            'excerpt' => [ 'nullable', 'string', 'max:1000' ],
            'author' => [ 'nullable', 'string', 'max:191' ],
            'body' => [ 'nullable', 'string', 'max:' . (int) config('cms.sanitizer.max_input_length', 2 * 1024 * 1024) ],
            'meta_title' => [ 'nullable', 'string', 'max:255' ],
            'meta_description' => [ 'nullable', 'string', 'max:320' ],
            'is_published' => [ 'nullable', 'boolean' ],
        ], [
            'slug.regex' => 'Het adres (slug) mag alleen kleine letters, cijfers en koppeltekens bevatten.',
        ], [
            'slug' => 'adres (slug)',
            'title' => 'titel',
            'excerpt' => 'samenvatting',
            'author' => 'auteur',
            'body' => 'tekst',
            'meta_title' => 'SEO-titel',
            'meta_description' => 'SEO-beschrijving',
        ])->validate();

        if ($post->exists) {
            $duplicate = PostTranslation::query()
                ->where('post_id', '=', $post->id)
                ->where('locale', '=', $translation->locale)
                ->when($translation->exists, function ($query) use ($translation) {
                    $query->where('id', '!=', $translation->id);
                })
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'locale' => 'Dit bericht heeft al een vertaling "' . $translation->locale . '".',
                ]);
            }
        }

        $taken = PostTranslation::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('locale', '=', $translation->locale)
            ->where('slug', '=', $translation->slug)
            ->when($translation->exists, function ($query) use ($translation) {
                $query->where('id', '!=', $translation->id);
            })
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'slug' => 'Er bestaat al een bericht met het adres "' . $translation->slug . '" in deze taal.',
            ]);
        }

        $translation->body = $this->sanitizer->sanitize((string) $translation->body);
        $translation->is_published = (bool) $translation->is_published;
        $translation->excerpt = $this->nullIfBlank($translation->excerpt);
        $translation->author = $this->nullIfBlank($translation->author);
        $translation->meta_title = $this->nullIfBlank($translation->meta_title);
        $translation->meta_description = $this->nullIfBlank($translation->meta_description);
    }

    /**
     * Forget everything cached from the organisation's posts.
     * @param Organisation $organisation
     */
    public function forgetCaches(Organisation $organisation): void
    {
        Cache::forget(SitemapController::cacheKey($organisation));
        $this->blog->forgetCaches($organisation->id);
    }

    /**
     * @param Post $post
     * @param array $attributes
     */
    protected function fillPost(Post $post, array $attributes): void
    {
        foreach (self::POST_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $post->{$field} = $attributes[$field];
            }
        }
    }

    /**
     * @param PostTranslation $translation
     * @param array $attributes
     */
    protected function fillTranslation(PostTranslation $translation, array $attributes): void
    {
        foreach (self::TRANSLATION_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $translation->{$field} = $attributes[$field];
            }
        }
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected function nullIfBlank($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Run $callback in a transaction, turn a unique index violation (a race
     * on the slug) into a validation error, and forget the caches afterwards.
     * @param Organisation|null $organisation
     * @param callable $callback
     * @return mixed
     * @throws ValidationException
     */
    protected function transaction(?Organisation $organisation, callable $callback)
    {
        try {
            $result = DB::transaction($callback);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'slug' => 'Dit adres bestaat al in deze taal.',
            ]);
        }

        if ($organisation) {
            $this->forgetCaches($organisation);
        }

        return $result;
    }
}
