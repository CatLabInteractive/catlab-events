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

namespace App\Services\Admin;

use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\CharonFrontend\Widgets\FileWidget;
use CatLab\CharonFrontend\Widgets\FormField;

/**
 * The Charon file widget for Asset relationships (posters, logos, headers),
 * with the existing assets shown as a grid of thumbnails instead of a select
 * of file names. Reading the form is unchanged: the grid posts the same
 * [id] value as the select did.
 *
 * Class AssetFileWidget
 * @package App\Services\Admin
 */
class AssetFileWidget extends FileWidget
{
    const THUMBNAIL_WIDTH = 240;

    protected $view = 'admin.widgets.asset-file';

    protected function getViewData(FormField $field): array
    {
        $data = parent::getViewData($field);

        $ids = array_filter(array_keys($data['options']), 'is_numeric');
        $assets = count($ids) > 0
            ? Asset::query()->whereIn('id', $ids)->get()->keyBy('id')
            : collect();

        $tiles = [];
        foreach ($data['options'] as $id => $label) {
            $asset = $assets->get($id);
            $tiles[] = [
                'id' => (string) $id,
                'label' => $label,
                'name' => $asset ? $asset->name : $label,
                'thumbnail' => $asset && $asset->isImage()
                    ? $asset->getUrl([ 'width' => self::THUMBNAIL_WIDTH ])
                    : null,
                'preview' => $asset && $asset->isImage()
                    ? $asset->getUrl([ 'width' => 320 ])
                    : null,
            ];
        }

        $data['tiles'] = $tiles;
        return $data;
    }
}
