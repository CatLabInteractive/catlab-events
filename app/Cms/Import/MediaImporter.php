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

namespace App\Cms\Import;

use App\Cms\CmsAssets;
use App\Models\CmsRedirect;
use App\Models\Organisation;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Copies WordPress uploads into central storage as assets of the
 * organisation, and writes a cms_redirects row from the old upload path to
 * the asset URL.
 *
 * - Only URLs under the site's own `/wp-content/uploads/` are fetched.
 * - For a resize (`foto-1024x768.jpg`) the original is tried first, the
 *   resize when there is no original.
 * - An upload imported in an earlier run is found again through its
 *   redirect row, so a re-run downloads nothing twice.
 *
 * Class MediaImporter
 * @package App\Cms\Import
 */
class MediaImporter
{
    /**
     * Content types that are imported (everything else is skipped).
     */
    const ALLOWED_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ];

    const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @var MediaUrl
     */
    private $mediaUrl;

    /**
     * @var CmsAssets
     */
    private $assets;

    /**
     * @var ImportReport
     */
    private $report;

    /**
     * URL => Asset|null for this run.
     * @var array
     */
    private $seen = [];

    /**
     * @param MediaUrl $mediaUrl
     * @param CmsAssets $assets
     * @param ImportReport $report
     */
    public function __construct(MediaUrl $mediaUrl, CmsAssets $assets, ImportReport $report)
    {
        $this->mediaUrl = $mediaUrl;
        $this->assets = $assets;
        $this->report = $report;
    }

    /**
     * The asset for an upload URL (normalised by MediaUrl), imported on first
     * use. Null when the download failed, or on a dry run for an upload that
     * was not imported before.
     * @param string $url
     * @param Organisation $organisation
     * @param bool $dryRun
     * @return Asset|null
     */
    public function import(string $url, Organisation $organisation, bool $dryRun = false): ?Asset
    {
        if (array_key_exists($url, $this->seen)) {
            return $this->seen[$url];
        }

        $asset = $this->existing($url, $organisation);
        if (!$asset) {
            $original = $this->mediaUrl->original($url);
            if ($original !== null) {
                $asset = $this->existing($original, $organisation) ?? ($this->seen[$original] ?? null);
            }
        }

        if (!$asset) {
            if ($dryRun) {
                $this->report->media[] = 'download ' . $url;
                return $this->seen[$url] = null;
            }
            $asset = $this->download($url, $organisation);
        }

        if ($asset && !$dryRun) {
            $this->writeRedirect($url, $asset, $organisation);
        }

        return $this->seen[$url] = $asset;
    }

    /**
     * The asset of an upload imported in an earlier run.
     * @param string $url
     * @param Organisation $organisation
     * @return Asset|null
     */
    private function existing(string $url, Organisation $organisation): ?Asset
    {
        $redirect = CmsRedirect::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('from_path', '=', $this->mediaUrl->path($url))
            ->first();
        if (!$redirect || !preg_match('#/assets/([^/?\#]+)#', $redirect->to_url, $match)) {
            return null;
        }

        return Asset::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('asset_key', '=', $match[1])
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * @param string $url
     * @param Organisation $organisation
     * @return Asset|null
     */
    private function download(string $url, Organisation $organisation): ?Asset
    {
        $original = $this->mediaUrl->original($url);
        $candidates = $original !== null ? [ $original, $url ] : [ $url ];

        foreach ($candidates as $candidate) {
            $response = $this->fetch($candidate);
            if (!$response) {
                continue;
            }

            $asset = $this->store($candidate, $response, $organisation);
            if ($asset) {
                if ($candidate !== $url) {
                    $this->seen[$candidate] = $asset;
                    $this->writeRedirect($candidate, $asset, $organisation);
                }
                $this->report->media[] = 'imported ' . $candidate;
                return $asset;
            }
        }

        $this->report->warnings[] = 'media not imported: ' . $url;

        return null;
    }

    /**
     * GET with three attempts and backoff; null on failure.
     * @param string $url
     * @return Response|null
     */
    private function fetch(string $url): ?Response
    {
        try {
            $response = Http::timeout(60)
                ->withOptions([ 'allow_redirects' => false ])
                ->retry(3, function (int $attempt) {
                    return $attempt * 1000;
                }, function ($exception) {
                    // Retry connection problems and server errors, not a 404.
                    return !($exception instanceof \Illuminate\Http\Client\RequestException)
                        || $exception->response->serverError();
                }, false)
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response : null;
    }

    /**
     * @param string $url
     * @param Response $response
     * @param Organisation $organisation
     * @return Asset|null
     */
    private function store(string $url, Response $response, Organisation $organisation): ?Asset
    {
        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $body = $response->body();
        if (!in_array($type, self::ALLOWED_TYPES, true) || $body === '' || strlen($body) > self::MAX_BYTES) {
            $this->report->warnings[] = 'media skipped (' . ($type ?: 'no type') . ', ' . strlen($body) . ' bytes): ' . $url;
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'wpimport');
        try {
            file_put_contents($path, $body);
            $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
            $file = new UploadedFile($path, $name, $type, null, true);

            return $this->assets->store($file, $organisation);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param string $url
     * @param Asset $asset
     * @param Organisation $organisation
     */
    private function writeRedirect(string $url, Asset $asset, Organisation $organisation): void
    {
        $from = $this->mediaUrl->path($url);
        if (strlen($from) > 191) {
            $this->report->warnings[] = 'upload path too long for a redirect: ' . $from;
            return;
        }

        $redirect = CmsRedirect::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('from_path', '=', $from)
            ->first() ?? new CmsRedirect();

        $redirect->organisation_id = $organisation->id;
        $redirect->from_path = $from;
        $redirect->to_url = $asset->getUrl();
        $redirect->status_code = 301;
        if ($redirect->isDirty()) {
            $redirect->save();
        }
    }
}
