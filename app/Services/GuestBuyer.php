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

namespace App\Services;

use App\Models\User;

/**
 * Class GuestBuyer
 *
 * The buyer of a guest checkout: the accounts user that owns the typed
 * address (found or created at accounts), mirrored as a local user.
 *
 * Typing an address proves nothing about owning it, so this NEVER logs
 * anyone in, and never changes an existing local user. The owner gets in
 * through the normal accounts login (for a checkout-created account: the
 * email-first completion mail).
 *
 * @package App\Services
 */
class GuestBuyer
{
    /**
     * @param string $email
     * @return User
     * @throws GuestBuyerNeedsLogin when a local account that was never linked
     *   to accounts holds the address
     * @throws \Throwable when accounts could not be reached
     */
    public function resolve(string $email): User
    {
        $client = app(CatLabApiClientFactory::class)->forUser(null);
        $catlabId = $client->findOrCreateUser($email, 'nl');

        /** @var User|null $user */
        $user = User::query()->where('catlab_id', '=', $catlabId)->first();
        if ($user) {
            return $user;
        }

        // users.email is unique and not nullable, so another local row can
        // still hold this address.
        $holders = User::query()->where('email', '=', $email)->get();
        foreach ($holders as $holder) {
            if ($holder->catlab_id === null) {
                // A local account never linked to accounts: it would never be
                // refreshed, so renaming it would break it for good. Its owner
                // logs in instead.
                throw new GuestBuyerNeedsLogin('Address held by a local account without catlab_id');
            }
        }

        // A stale copy from before its owner changed their address at
        // accounts. Accounts says the address is this catlab_id's now, so
        // move the stale row out of the way; its next login refreshes it.
        foreach ($holders as $stale) {
            $stale->email = 'stale-' . $stale->id . '@invalid';
            $stale->save();
        }

        $user = new User();
        $user->catlab_id = $catlabId;
        $user->email = $email;
        $user->name = strstr($email, '@', true) ?: $email;
        // Required by the schema; never usable (accounts handles login).
        $user->password = bcrypt(bin2hex(random_bytes(32)));
        try {
            $user->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // A concurrent checkout for the same address won the insert.
            $existing = User::query()->where('catlab_id', '=', $catlabId)->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        // User::boot() makes the very first user an admin. A guest buyer
        // must never be one.
        if ($user->admin) {
            $user->admin = 0;
            $user->save();
        }

        return $user;
    }
}
