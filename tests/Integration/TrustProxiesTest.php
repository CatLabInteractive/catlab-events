<?php

namespace Tests\Integration;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Client IP behind Cloudflare + a local reverse proxy (security review
 * 2026-11-17): a visitor must not be able to choose their own IP.
 */
class TrustProxiesTest extends IntegrationTestCase
{
    private function clientIp(string $remoteAddr, array $headers = []): array
    {
        $server = [ 'REMOTE_ADDR' => $remoteAddr ];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $request = Request::create('http://tickets.example.test/events/1/register', 'GET', [], [], [], $server);

        return (new TrustProxies())->handle($request, function (Request $request) {
            return [ $request->ip(), $request->isSecure() ];
        });
    }

    public function testVisitorSuppliedForwardedForIsIgnored()
    {
        // Visitor sends a fake, Cloudflare appends the real IP, the local
        // proxy appends the edge address.
        [ $ip ] = $this->clientIp('172.17.0.1', [ 'X-Forwarded-For' => '6.6.6.6, 203.0.113.9, 162.158.1.1' ]);
        $this->assertSame('203.0.113.9', $ip);

        // Straight from a Cloudflare edge, no local proxy.
        [ $ip ] = $this->clientIp('162.158.1.1', [ 'X-Forwarded-For' => '6.6.6.6, 203.0.113.9' ]);
        $this->assertSame('203.0.113.9', $ip);
    }

    public function testCloudflareConnectingIpWhenTheLocalProxyOverwritesForwardedFor()
    {
        [ $ip, $secure ] = $this->clientIp('172.17.0.1', [
            'X-Forwarded-For' => '162.158.1.1',
            'X-Forwarded-Proto' => 'https',
            'CF-Connecting-IP' => '203.0.113.9',
        ]);
        $this->assertSame('203.0.113.9', $ip);
        $this->assertTrue($secure, 'X-Forwarded-Proto from the trusted proxy still counts');
    }

    public function testHeadersAreIgnoredWhenNotComingThroughCloudflare()
    {
        // Hitting the origin directly from a public address.
        [ $ip, $secure ] = $this->clientIp('198.51.100.7', [
            'X-Forwarded-For' => '1.2.3.4',
            'X-Forwarded-Proto' => 'https',
            'CF-Connecting-IP' => '1.2.3.4',
        ]);
        $this->assertSame('198.51.100.7', $ip);
        $this->assertFalse($secure);

        // Through the local proxy, but not through Cloudflare.
        [ $ip ] = $this->clientIp('172.17.0.1', [
            'X-Forwarded-For' => '198.51.100.7',
            'CF-Connecting-IP' => '1.2.3.4',
        ]);
        $this->assertSame('198.51.100.7', $ip);
    }

    public function testExtraTrustedProxiesFromConfig()
    {
        config([ 'app.trusted_proxies' => '198.51.100.0/24' ]);
        [ $ip ] = $this->clientIp('198.51.100.7', [ 'X-Forwarded-For' => '203.0.113.9' ]);
        $this->assertSame('203.0.113.9', $ip);
    }
}
