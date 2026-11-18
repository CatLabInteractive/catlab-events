<?php

namespace App\Services\Admin;

use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\UploadHandler;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Stores the files of the admin's file fields (posters, logos, ...) in
 * CentralStorage, as Assets of the active organisation. The picker only
 * offers that organisation's assets.
 */
class CentralStorageUploadHandler implements UploadHandler
{
    /**
     * How many existing assets the "pick an existing file" list offers.
     */
    const PICKER_LIMIT = 200;

    public function store(UploadedFile $file, FormField $field)
    {
        return self::storeForActiveOrganisation($file)->id;
    }

    /**
     * Store an upload as an asset of the logged in user's active
     * organisation.
     *
     * @param UploadedFile $file
     * @return Asset
     */
    public static function storeForActiveOrganisation(UploadedFile $file): Asset
    {
        $asset = \CentralStorage::store($file);

        $user = \Auth::user();
        if ($user) {
            $asset->user_id = $user->id;
            $asset->organisation_id = $user->getActiveOrganisation()->id;
        }

        $asset->save();

        return $asset;
    }

    /**
     * The form that came with the upload failed to save: remove the asset,
     * here and on the storage server.
     *
     * @param mixed $id
     */
    public function discard($id): void
    {
        $asset = Asset::find($id);
        if ($asset) {
            $asset->delete();
        }
    }

    public function getPreviewUrl($id): ?string
    {
        $asset = Asset::find($id);
        return $asset ? $asset->getUrl([ 'width' => 320 ]) : null;
    }

    public function getOptions(Request $request, FormField $field): array
    {
        $organisation = $request->user() ? $request->user()->getActiveOrganisation() : null;
        if (!$organisation) {
            return [];
        }

        $assets = Asset::query()
            ->where('organisation_id', '=', $organisation->id)
            ->orderByDesc('id')
            ->limit(self::PICKER_LIMIT)
            ->get();

        $current = $field->getValue();
        if ($current && !$assets->contains('id', $current) && $asset = Asset::find($current)) {
            $assets->prepend($asset);
        }

        $options = [];
        foreach ($assets as $asset) {
            $options[$asset->id] = $this->label($asset);
        }
        return $options;
    }

    /**
     * @param Asset $asset
     * @return string
     */
    private function label(Asset $asset): string
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
