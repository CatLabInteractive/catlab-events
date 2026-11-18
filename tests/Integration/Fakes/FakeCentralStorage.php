<?php

namespace Tests\Integration\Fakes;

use CatLab\CentralStorage\Client\CentralStorageClient;
use CatLab\CentralStorage\Client\Models\Asset;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Central storage without the network: store() records the call and
 * returns an Asset built from the file (not yet saved, like the real
 * client), getUrl() points at https://storage.test.
 */
class FakeCentralStorage extends CentralStorageClient
{
    const FRONT = 'https://storage.test';

    /**
     * @var File[]
     */
    public $stored = [];

    /**
     * @var Asset[]
     */
    public $deleted = [];

    public function __construct()
    {
        parent::__construct(self::FRONT, 'test-key', 'test-secret');
        $this->setFrontUrl(self::FRONT);
    }

    public function store(File $file, $attributes = [], $server = null, $key = null, $secret = null)
    {
        $this->stored[] = $file;

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();

        $asset = new Asset();
        $asset->setAssetKey('fake' . bin2hex(random_bytes(8)));
        $asset->setName($name);
        $asset->setMimeType($mimeType);
        $asset->setType(strpos($mimeType, 'image/') === 0 ? 'image' : 'document');
        $asset->setSize($file->getSize());

        $dimensions = @getimagesize($file->getPathname());
        if ($dimensions) {
            $asset->setDimensions($dimensions[0], $dimensions[1]);
        }

        return $asset;
    }

    public function deleteAsset(Asset $asset, array $properties = [], $server = null, $key = null, $secret = null)
    {
        $this->deleted[] = $asset;

        return true;
    }
}
