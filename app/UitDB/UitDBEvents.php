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

namespace App\UitDB;

use App\Models\Event;
use App\Models\EventDate;
use App\Models\TicketCategory;

/**
 * Class UitDBEvents
 * @package App\UitDB
 */
class UitDBEvents
{
    /**
     * Default UiTDatabank event type term ID for "Concert"
     */
    const DEFAULT_EVENT_TYPE_ID = '0.50.21.0.0';

    /**
     * Default event duration in hours when no end date is specified
     */
    const DEFAULT_EVENT_DURATION_HOURS = 3;
    /**
     * @var UitDatabankService
     */
    private $uitDatabankService;

    /**
     * UitDBEvents constructor.
     * @param UitDatabankService $uitDatabankService
     */
    public function __construct(UitDatabankService $uitDatabankService)
    {
        $this->uitDatabankService = $uitDatabankService;
    }

    /**
     * Upload or update an event to the uitdatabank and return the event id.
     * @param Event $event
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function upload(Event $event)
    {
        if (!$this->uitDatabankService->hasClientCredentials()) {
            return null;
        }

        $existingId = $event->getUitDBId();

        if ($existingId) {
            $uitdbEventId = $this->updateEvent($event, $existingId);
        } else {
            $uitdbEventId = $this->createEvent($event);
        }

        // Upload images after the event is created/updated
        if ($uitdbEventId) {
            $this->uploadImages($event, $uitdbEventId);
        }

        return $uitdbEventId;
    }

    /**
     * Create a new event in UiTDatabank.
     * @param Event $event
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function createEvent(Event $event)
    {
        $payload = $this->buildEventPayload($event);

        $response = $this->uitDatabankService->entryApiRequest(
            'POST',
            '/events',
            [
                'json' => $payload
            ]
        );

        if (isset($response['eventId'])) {
            $event->uitdb_event_id = $response['eventId'];
            $event->save();
            return $response['eventId'];
        }

        // Some API versions return the id in a different field
        if (isset($response['id'])) {
            $event->uitdb_event_id = $response['id'];
            $event->save();
            return $response['id'];
        }

        return null;
    }

    /**
     * Update an existing event in UiTDatabank.
     * @param Event $event
     * @param string $uitdbEventId
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function updateEvent(Event $event, $uitdbEventId)
    {
        $payload = $this->buildEventPayload($event);

        $this->uitDatabankService->entryApiRequest(
            'PUT',
            '/events/' . $uitdbEventId,
            [
                'json' => $payload
            ]
        );

        return $uitdbEventId;
    }

    /**
     * Build the event payload for the UiTDatabank Entry API.
     * @param Event $event
     * @return array
     */
    protected function buildEventPayload(Event $event)
    {
        $payload = [
            'mainLanguage' => 'nl',
            'name' => [
                'nl' => $event->name,
            ],
            'calendarType' => $this->getCalendarType($event),
            'terms' => [
                [
                    'id' => self::DEFAULT_EVENT_TYPE_ID,
                    'domain' => 'eventtype',
                ]
            ],
        ];

        // Add description if available
        if ($event->description) {
            $payload['description'] = [
                'nl' => strip_tags($event->description),
            ];
        }

        // Add location if venue is set
        if ($event->venue) {
            $payload['location'] = $this->buildLocationPayload($event);
        }

        // Add calendar info
        $calendarPayload = $this->buildCalendarPayload($event);
        $payload = array_merge($payload, $calendarPayload);

        // Add price info
        $priceInfo = $this->buildPriceInfoPayload($event);
        if (!empty($priceInfo)) {
            $payload['priceInfo'] = $priceInfo;
        }

        return $payload;
    }

    /**
     * Determine the calendar type for the event.
     * @param Event $event
     * @return string
     */
    protected function getCalendarType(Event $event)
    {
        $eventDates = $event->eventDates;

        if ($eventDates->count() > 1) {
            return 'multiple';
        }

        return 'single';
    }

    /**
     * Build calendar payload based on event dates.
     * @param Event $event
     * @return array
     */
    protected function buildCalendarPayload(Event $event)
    {
        $eventDates = $event->eventDates;

        if ($eventDates->count() === 0) {
            return [];
        }

        $subEvents = [];
        foreach ($eventDates as $eventDate) {
            /** @var EventDate $eventDate */
            $subEvent = [
                'startDate' => $eventDate->startDate->toIso8601String(),
                'endDate' => $eventDate->endDate
                    ? $eventDate->endDate->toIso8601String()
                    : $eventDate->startDate->copy()->addHours(self::DEFAULT_EVENT_DURATION_HOURS)->toIso8601String(),
            ];
            $subEvents[] = $subEvent;
        }

        return [
            'subEvent' => $subEvents,
        ];
    }

    /**
     * Build location payload from event venue.
     * @param Event $event
     * @return array
     */
    protected function buildLocationPayload(Event $event)
    {
        $venue = $event->venue;

        $location = [
            'name' => [
                'nl' => $venue->name,
            ],
            'address' => [
                'nl' => [
                    'streetAddress' => $venue->address,
                    'postalCode' => $venue->postalCode,
                    'addressLocality' => $venue->city,
                    'addressCountry' => $venue->country ?? 'BE',
                ]
            ],
        ];

        return $location;
    }

    /**
     * Upload images (poster and logo) to UiTDatabank for the given event.
     * @param Event $event
     * @param string $uitdbEventId
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function uploadImages(Event $event, $uitdbEventId)
    {
        $images = $this->getEventImageUrls($event);

        foreach ($images as $image) {
            $this->addImageToEvent($uitdbEventId, $image['url'], $image['description'], $image['copyrightHolder']);
        }
    }

    /**
     * Get the image URLs for an event.
     * @param Event $event
     * @return array
     */
    protected function getEventImageUrls(Event $event)
    {
        $images = [];

        if ($event->poster && $event->poster->getUrl()) {
            $images[] = [
                'url' => $event->poster->getUrl(),
                'description' => $event->name,
                'copyrightHolder' => $event->organisation->name ?? $event->name,
            ];
        }

        if ($event->logo && $event->logo->getUrl()) {
            $images[] = [
                'url' => $event->logo->getUrl(),
                'description' => $event->name . ' - logo',
                'copyrightHolder' => $event->organisation->name ?? $event->name,
            ];
        }

        return $images;
    }

    /**
     * Add an image to an event in UiTDatabank via media object upload.
     * @param string $uitdbEventId
     * @param string $imageUrl
     * @param string $description
     * @param string $copyrightHolder
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function addImageToEvent($uitdbEventId, $imageUrl, $description, $copyrightHolder)
    {
        // First, create a media object by URL
        $mediaResponse = $this->uitDatabankService->entryApiRequest(
            'POST',
            '/images',
            [
                'json' => [
                    'url' => $imageUrl,
                    'language' => 'nl',
                    'description' => $description,
                    'copyrightHolder' => $copyrightHolder,
                ]
            ]
        );

        $mediaObjectId = $mediaResponse['imageId'] ?? $mediaResponse['mediaObjectId'] ?? $mediaResponse['id'] ?? null;

        if (!$mediaObjectId) {
            \Log::warning('UiTDatabank: Could not extract media object ID from image upload response for event ' . $uitdbEventId);
            return;
        }

        // Then, link the media object to the event
        $this->uitDatabankService->entryApiRequest(
            'POST',
            '/events/' . $uitdbEventId . '/images',
            [
                'json' => [
                    'mediaObjectId' => $mediaObjectId,
                ]
            ]
        );
    }

    /**
     * Build price info payload from ticket categories.
     * @param Event $event
     * @return array
     */
    protected function buildPriceInfoPayload(Event $event)
    {
        $priceInfo = [];

        foreach ($event->ticketCategories as $ticketCategory) {
            /** @var TicketCategory $ticketCategory */
            $priceInfo[] = [
                'category' => 'base',
                'name' => [
                    'nl' => $ticketCategory->name,
                ],
                'price' => floatval($ticketCategory->price),
                'priceCurrency' => 'EUR',
            ];
        }

        return $priceInfo;
    }
}
