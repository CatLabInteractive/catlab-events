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

namespace App\Policies;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;

/**
 * Translations of blog posts: admins of the post's organisation only, like
 * PostPolicy. The index and create checks get the parent post.
 *
 * Class PostTranslationPolicy
 * @package App\Policies
 */
class PostTranslationPolicy
{
    /**
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function index(User $user, Post $post)
    {
        return $this->isPostAdmin($user, $post);
    }

    /**
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function create(User $user, Post $post)
    {
        return $this->isPostAdmin($user, $post);
    }

    /**
     * @param User $user
     * @param PostTranslation $translation
     * @return bool
     */
    public function view(User $user, PostTranslation $translation)
    {
        return $this->isPostAdmin($user, $translation->post);
    }

    /**
     * @param User $user
     * @param PostTranslation $translation
     * @return bool
     */
    public function edit(User $user, PostTranslation $translation)
    {
        return $this->isPostAdmin($user, $translation->post);
    }

    /**
     * @param User $user
     * @param PostTranslation $translation
     * @return bool
     */
    public function destroy(User $user, PostTranslation $translation)
    {
        return $this->isPostAdmin($user, $translation->post);
    }

    /**
     * @param User $user
     * @param Post|null $post
     * @return bool
     */
    protected function isPostAdmin(User $user, ?Post $post)
    {
        return $post && $post->organisation && $post->organisation->isAdmin($user);
    }
}
