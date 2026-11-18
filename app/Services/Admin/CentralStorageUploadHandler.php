<?php

namespace App\Services\Admin;

use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\UploadHandler;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Stores the files of the admin's file fields (posters, logos, ...) in
 * CentralStorage, as Assets.
 */
class CentralStorageUploadHandler implements UploadHandler
{
    /**
     * How many existing assets the "pick an existing file" list offers.
     */
    const PICKER_LIMIT = 200;

    public function store(UploadedFile $file, FormField $field)
    {
        $asset = \CentralStorage::store($file);
        $asset->save();

        return $asset->id;
    }

    public function getPreviewUrl($id): ?string
    {
        $asset = Asset::find($id);
        return $asset ? $asset->getUrl([ 'width' => 320 ]) : null;
    }

    public function getOptions(Request $request, FormField $field): array
    {
        $assets = Asset::query()
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
