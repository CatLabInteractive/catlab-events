<?php

namespace App\Services;

use App\Models\EventDate;
use App\Models\Order;

/**
 * Builds an iCalendar (.ics) file with the dates an order gives access to,
 * so the buyer can add the quiz to their agenda from the thanks page.
 */
class OrderCalendar
{
    /**
     * @param Order $order
     * @return string
     */
    public function render(Order $order): string
    {
        $event = $order->event;
        $venue = $event->venue;
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'catlab-events';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CatLab//Events//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        $eventDates = $order->ticketCategory->eventDates->sortBy('startDate');
        foreach ($eventDates as $eventDate) {
            /** @var EventDate $eventDate */
            if (!$eventDate->startDate) {
                continue;
            }

            $description = [];
            if ($eventDate->doorsDate) {
                $description[] = 'Deuren open om ' . $eventDate->doorsDate->format('H:i') . '.';
            }
            if ($order->group) {
                $description[] = 'Team: ' . $order->group->name;
            }
            $description[] = $event->getUrl();

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:order-' . $order->id . '-date-' . $eventDate->id . '@' . $host;
            $lines[] = 'DTSTAMP:' . $this->formatDate(now());
            $lines[] = 'DTSTART:' . $this->formatDate($eventDate->startDate);
            if ($eventDate->endDate && $eventDate->endDate > $eventDate->startDate) {
                $lines[] = 'DTEND:' . $this->formatDate($eventDate->endDate);
            }
            $lines[] = 'SUMMARY:' . $this->escape($event->name);
            if ($venue) {
                $lines[] = 'LOCATION:' . $this->escape($venue->getAddressFull());
            }
            $lines[] = 'DESCRIPTION:' . $this->escape(implode("\n", $description));
            $lines[] = 'URL:' . $event->getUrl();
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([ $this, 'fold' ], $lines)) . "\r\n";
    }

    /**
     * @param \DateTimeInterface $date
     * @return string
     */
    private function formatDate(\DateTimeInterface $date): string
    {
        return (new \DateTime('@' . $date->getTimestamp()))->format('Ymd\THis\Z');
    }

    /**
     * Escape a TEXT value (RFC 5545 section 3.3.11).
     */
    private function escape(string $value): string
    {
        return str_replace(
            [ '\\', ';', ',', "\r\n", "\n" ],
            [ '\\\\', '\;', '\,', '\n', '\n' ],
            $value
        );
    }

    /**
     * Fold content lines longer than 75 octets (RFC 5545 section 3.1),
     * without splitting a multibyte character.
     */
    private function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > 75) {
                // The leading space of a continuation line counts towards its 75 octets.
                $out .= $current . "\r\n";
                $current = ' ';
            }
            $current .= $char;
        }
        return $out . $current;
    }
}
