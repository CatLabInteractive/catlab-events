<?php

namespace App\Services;

use GuzzleHttp\Client;

/**
 * Class Recaptcha
 *
 * Server-side check of a reCAPTCHA v2 response (guest checkout form).
 * Without a configured secret the check passes, so local development and
 * the test suite need no Google keys; production must configure one (a
 * warning is logged when it doesn't).
 *
 * @package App\Services
 */
class Recaptcha
{
    const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty(config('services.recaptcha.secret'));
    }

    /**
     * @return string|null
     */
    public function getSiteKey(): ?string
    {
        return $this->isEnabled() ? config('services.recaptcha.site_key') : null;
    }

    /**
     * @param string|null $response the g-recaptcha-response form field
     * @param string|null $ip
     * @return bool
     */
    public function verify(?string $response, ?string $ip = null): bool
    {
        if (!$this->isEnabled()) {
            if (app()->environment('production')) {
                \Log::warning('reCAPTCHA is not configured: the guest checkout form is unprotected (set RECAPTCHA_SECRET).');
            }
            return true;
        }

        if (!$response) {
            return false;
        }

        try {
            $result = (new Client([ 'timeout' => 5 ]))->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret' => config('services.recaptcha.secret'),
                    'response' => $response,
                    'remoteip' => $ip
                ]
            ]);

            $data = json_decode((string) $result->getBody(), true);
            return is_array($data) && !empty($data['success']);
        } catch (\Throwable $e) {
            \Log::warning('reCAPTCHA verification failed', [ 'error' => $e->getMessage() ]);
            return false;
        }
    }
}
