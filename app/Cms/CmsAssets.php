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

use App\Models\Organisation;
use App\Models\User;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * Images for CMS content: stored through central storage (the call
 * Admin\AssetController makes) and scoped to an organisation through
 * assets.organisation_id. Used by the admin editor (TinyMCE and the block
 * image picker) and the API upload endpoint, so both validate and store
 * the same way.
 *
 * Class CmsAssets
 * @package App\Cms
 */
class CmsAssets
{
    /**
     * Validation rules for an uploaded image (10 MB, no SVG).
     */
    const UPLOAD_RULES = [ 'file' => [ 'required', 'file', 'image', 'max:10240' ] ];

    /**
     * How many images the picker lists.
     */
    const LIST_LIMIT = 60;

    /**
     * @param UploadedFile $file
     * @param Organisation $organisation
     * @param User|null $user
     * @return Asset
     */
    public function store(UploadedFile $file, Organisation $organisation, ?User $user = null): Asset
    {
        if (!$file->isValid()) {
            abort(400, 'File not valid: ' . $file->getErrorMessage());
        }

        /** @var Asset $asset */
        $asset = \CentralStorage::store($file);
        $asset->organisation_id = $organisation->id;
        if ($user) {
            $asset->user_id = $user->id;
        }
        $asset->save();

        return $asset;
    }

    /**
     * The organisation's images, newest first, optionally filtered by name.
     * @param Organisation $organisation
     * @param string|null $search
     * @return Builder
     */
    public function images(Organisation $organisation, ?string $search = null): Builder
    {
        return Asset::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('type', '=', 'image')
            ->whereNull('deleted_at')
            ->when($search !== null && trim($search) !== '', function (Builder $query) use ($search) {
                $query->where('name', 'like', '%' . addcslashes(trim($search), '%_\\') . '%');
            })
            ->orderBy('id', 'desc');
    }

    /**
     * JSON shape of an asset for the editor. `location` is the key TinyMCE
     * reads after an upload.
     * @param Asset $asset
     * @return array
     */
    public function present(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'width' => $asset->width,
            'height' => $asset->height,
            'location' => $asset->getUrl(),
            'thumbnail' => $asset->getUrl([ 'width' => 320 ]),
        ];
    }
}
