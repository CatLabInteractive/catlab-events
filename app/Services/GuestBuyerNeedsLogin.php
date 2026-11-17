<?php

namespace App\Services;

/**
 * The typed address belongs to a local account that was never linked to
 * accounts: a guest checkout must not touch it, so its owner logs in.
 */
class GuestBuyerNeedsLogin extends \RuntimeException
{
}
