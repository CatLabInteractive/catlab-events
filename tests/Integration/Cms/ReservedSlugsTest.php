<?php

namespace Tests\Integration\Cms;

use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RedirectController;
use Illuminate\Routing\Route;
use Tests\Integration\IntegrationTestCase;

/**
 * The CMS page route is a catch-all registered last. Every top-level path
 * of another route must be a reserved slug, so an editor can never create
 * a page that is silently shadowed, and nothing may be registered after the
 * CMS routes.
 */
class ReservedSlugsTest extends IntegrationTestCase
{
    private function isCmsRoute(Route $route): bool
    {
        $action = $route->getActionName();

        return str_starts_with($action, PageController::class . '@')
            || str_starts_with($action, PostController::class . '@')
            || str_starts_with($action, RedirectController::class . '@');
    }

    public function testEveryTopLevelRouteSegmentIsReserved()
    {
        $reserved = config('cms.reserved_slugs');
        $missing = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($this->isCmsRoute($route) || $route->getDomain()) {
                continue;
            }

            $segment = explode('/', trim($route->uri(), '/'))[0];

            // Parameters and segments that can never be a page slug
            // (e.g. "_debugbar") cannot collide with a page.
            if ($segment === '' || str_starts_with($segment, '{') || !preg_match('/^[a-z0-9\-]+$/', $segment)) {
                continue;
            }

            if (!in_array($segment, $reserved, true)) {
                $missing[$segment] = $route->uri();
            }
        }

        $this->assertSame([], $missing, 'Add these top-level segments to config/cms.php reserved_slugs.');
    }

    public function testLocalesAreReserved()
    {
        foreach (config('cms.locales') as $locale) {
            $this->assertContains($locale, config('cms.reserved_slugs'));
        }
    }

    public function testCmsRoutesAreRegisteredLast()
    {
        $routes = array_values(array_filter(
            app('router')->getRoutes()->getRoutes(),
            function (Route $route) {
                return !$route->isFallback;
            }
        ));

        $firstCms = null;
        foreach ($routes as $index => $route) {
            if ($this->isCmsRoute($route)) {
                $firstCms = $index;
                break;
            }
        }

        $this->assertNotNull($firstCms, 'CMS routes are not registered.');

        foreach (array_slice($routes, $firstCms) as $route) {
            $this->assertTrue(
                $this->isCmsRoute($route),
                'Route "' . $route->uri() . '" is registered after the CMS catch-all and will be shadowed.'
            );
        }

        $fallbacks = array_filter(app('router')->getRoutes()->getRoutes(), function (Route $route) {
            return $route->isFallback;
        });
        $this->assertCount(1, $fallbacks);
        $this->assertSame(RedirectController::class . '@fallback', array_values($fallbacks)[0]->getActionName());
    }
}
