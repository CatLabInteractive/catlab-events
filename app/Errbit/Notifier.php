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

namespace App\Errbit;

use Airbrake\Notifier as AirbrakeNotifier;

/**
 * Class Notifier
 *
 * phpbrake 0.8.0 (the last release that supports PHP 8.0) checks its
 * options with empty(), which silently flips remoteConfig=false back to
 * true. The notifier then phones home to airbrake.io for configuration,
 * which disables all notifications when talking to a self-hosted Errbit.
 * This subclass re-applies the option after construction.
 *
 * @package App\Errbit
 */
class Notifier extends AirbrakeNotifier
{
    /**
     * @param array $opt
     * @throws \Airbrake\Exception
     */
    public function __construct($opt)
    {
        parent::__construct($opt);

        if (array_key_exists('remoteConfig', $opt)) {
            $this->opt['remoteConfig'] = $opt['remoteConfig'];
        }
    }
}
