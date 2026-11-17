<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Class TrustProxies
 *
 * Production runs behind Cloudflare and a local reverse proxy (dokku's
 * nginx, on a private address). Only those are trusted: trusting every
 * address let a visitor pick their own IP with a forged X-Forwarded-For
 * (Cloudflare appends to that header, it does not replace it), which made
 * per-IP rate limits useless (guest checkout security review 2026-11-17).
 *
 * Symfony walks X-Forwarded-For from the right and skips trusted hops, so
 * the client IP is the address Cloudflare appended, never a value the
 * visitor sent. If the local proxy overwrites X-Forwarded-For with the
 * Cloudflare edge address instead, Cloudflare's own CF-Connecting-IP header
 * is used, but only when the request really came through Cloudflare.
 *
 * Extra proxies: TRUSTED_PROXIES (comma separated IPs / CIDRs).
 */
class TrustProxies extends Middleware
{
    /**
     * Private and loopback ranges: the local reverse proxy / docker network.
     */
    const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1/128',
        'fc00::/7',
    ];

    /**
     * https://www.cloudflare.com/ips/ (update when Cloudflare announces changes).
     */
    const CLOUDFLARE_RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * @return array
     */
    protected function proxies()
    {
        $extra = array_filter(array_map('trim', explode(',', (string) config('app.trusted_proxies'))));

        return array_values(array_merge(self::PRIVATE_RANGES, self::CLOUDFLARE_RANGES, $extra));
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        return parent::handle($request, function (Request $request) use ($next) {
            $this->useCloudflareConnectingIp($request);
            return $next($request);
        });
    }

    /**
     * When the nearest untrusted hop is still Cloudflare (the local proxy
     * replaced X-Forwarded-For with the edge address), take the visitor's
     * address from CF-Connecting-IP. Never when the request did not come
     * through Cloudflare: then that header is the sender's own invention.
     *
     * @param Request $request
     */
    protected function useCloudflareConnectingIp(Request $request)
    {
        $connectingIp = $request->headers->get('CF-Connecting-IP');
        if (!$connectingIp || !filter_var($connectingIp, FILTER_VALIDATE_IP)) {
            return;
        }

        $clientIp = $request->getClientIp();
        if (!$clientIp || !IpUtils::checkIp($clientIp, self::CLOUDFLARE_RANGES)) {
            return;
        }

        // The edge address is trusted, so a single X-Forwarded-For entry
        // holding the visitor's address resolves to it.
        $request->headers->set('X-Forwarded-For', $connectingIp);
    }
}
