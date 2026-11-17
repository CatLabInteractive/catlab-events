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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Shared base for the admin CRUD controllers: carries the FrontCrudController
 * trait and the admin layout, so the controllers themselves only declare what
 * is actually theirs (their api controller, their child controllers, their
 * extra table actions).
 *
 * It also turns the linkable relationships to an Asset (posters, logos,
 * headers, ...) into a file field: preview, upload, pick an existing asset
 * or remove. Uploads are stored in CentralStorage before the form input is
 * handed to Charon, which then links the asset like any other relationship.
 *
 * @package App\Http\Controllers\Admin
 */
abstract class BaseAdminController extends Controller implements FrontCrudControllerContract
{
    use FrontCrudController {
        createFormView as charonCreateFormView;
        transformFormInput as charonTransformFormInput;
        resolveValues as charonResolveValues;
    }

    /**
     * How many existing assets the "pick an existing file" list offers.
     */
    const ASSET_PICKER_LIMIT = 200;

    public function __construct()
    {
        $this->setLayout('layouts.admin');
    }

    /**
     * Render asset relationships with our own file field; every other field
     * is still rendered by charon-frontend's own form-fields template.
     *
     * @param string $action
     * @param string $view
     * @param array $properties
     * @return \Illuminate\View\View
     */
    protected function createFormView($action, $view, $properties)
    {
        $linkables = [];
        $assetFields = [];

        foreach ($properties['linkables'] as $linkable) {
            if ($this->isAssetRelationship($linkable['field'])) {
                $assetFields[] = $this->describeAssetField($linkable['field'], $properties['resource'] ?? null);
            } else {
                $linkables[] = $linkable;
            }
        }

        if (count($assetFields) === 0) {
            return $this->charonCreateFormView($action, $view, $properties);
        }

        $properties['linkables'] = $linkables;
        $properties['assetFields'] = $assetFields;

        return $this->charonCreateFormView($action, 'admin.crud.form', $properties);
    }

    /**
     * Asset options come from describeAssetField(); don't load every asset
     * ever uploaded just to throw them away.
     *
     * @param RelationshipField $field
     * @return string[]
     */
    protected function resolveValues(RelationshipField $field)
    {
        if ($this->isAssetRelationship($field)) {
            return [];
        }

        return $this->charonResolveValues($field);
    }

    /**
     * Turn the file fields into the linkable input charon-frontend expects:
     * an uploaded file is stored and linked, "remove" unlinks, otherwise the
     * picked existing asset (if any) is linked.
     *
     * @param Request $request
     * @return Request
     */
    protected function transformFormInput(Request $request)
    {
        $assetFields = array_filter(explode(',', (string) $request->input('assetFields')));

        if (count($assetFields) > 0) {
            $request->validate([
                'assets.*.file' => 'nullable|file|mimetypes:image/*',
            ], [
                'assets.*.file.mimetypes' => 'Only images can be uploaded.',
            ]);

            $linkable = $request->input('linkable', []);
            $linkableFields = array_filter(explode(',', (string) $request->input('linkableFields')));

            foreach ($assetFields as $name) {
                $linkable[$name] = [ 'id' => $this->resolveAssetInput($request, $name) ];
                $linkableFields[] = $name;
            }

            $request->merge([
                'linkable' => $linkable,
                'linkableFields' => implode(',', array_unique($linkableFields)),
            ]);
        }

        return $this->charonTransformFormInput($request);
    }

    /**
     * @param Request $request
     * @param string $name
     * @return int|string|null Asset id; empty unlinks.
     */
    protected function resolveAssetInput(Request $request, string $name)
    {
        $file = $request->file('assets.' . $name . '.file');
        if ($file instanceof UploadedFile) {
            $asset = \CentralStorage::store($file);
            $asset->save();

            return $asset->id;
        }

        if ($request->input('assets.' . $name . '.remove')) {
            return null;
        }

        return $request->input('assets.' . $name . '.id');
    }

    /**
     * @param RelationshipField $field
     * @return bool
     */
    protected function isAssetRelationship($field)
    {
        return $field instanceof RelationshipField
            && is_a($field->getChildResource()->getEntityClassName(), Asset::class, true);
    }

    /**
     * Everything the file field partial needs.
     *
     * @param RelationshipField $field
     * @param \CatLab\Charon\Models\RESTResource|null $resource
     * @return array
     */
    protected function describeAssetField(RelationshipField $field, $resource = null)
    {
        $name = $field->getDisplayName();

        $current = null;
        if ($resource && $property = $resource->getProperties()->getProperty($field)) {
            $value = $property->getValue();
            if (is_array($value) && !empty($value['id'])) {
                $current = Asset::find($value['id']);
            }
        }

        $assets = Asset::query()
            ->orderByDesc('id')
            ->limit(self::ASSET_PICKER_LIMIT)
            ->get();

        if ($current && !$assets->contains('id', $current->id)) {
            $assets->prepend($current);
        }

        $options = [];
        foreach ($assets as $asset) {
            $options[$asset->id] = $this->assetLabel($asset);
        }

        return [
            'name' => $name,
            'label' => ucfirst(str_replace('_', ' ', $name)),
            'current' => $current,
            'currentUrl' => $current ? $current->getUrl([ 'width' => 320 ]) : null,
            'options' => $options,
        ];
    }

    /**
     * @param Asset $asset
     * @return string
     */
    protected function assetLabel(Asset $asset)
    {
        $details = [];
        if ($asset->width && $asset->height) {
            $details[] = $asset->width . '×' . $asset->height;
        }
        if ($asset->created_at) {
            $details[] = $asset->created_at->format('Y-m-d');
        }

        return $asset->name . (count($details) ? ' (' . implode(', ', $details) . ')' : '');
    }
}
