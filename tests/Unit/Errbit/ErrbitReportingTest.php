<?php

namespace Tests\Unit\Errbit;

use Airbrake\Notifier;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrbitReportingTest extends TestCase
{
    protected function enableErrbit()
    {
        config([
            'services.errbit.enabled' => true,
            'services.errbit.host' => 'https://errors.example.com',
            'services.errbit.project_id' => 1,
            'services.errbit.project_key' => 'test-key',
        ]);
    }

    public function testReportedExceptionIsSentToErrbit()
    {
        $this->enableErrbit();

        $notifier = $this->createMock(Notifier::class);
        $notifier->expects($this->once())->method('notify');
        $this->app->instance(Notifier::class, $notifier);

        $this->app->make(ExceptionHandler::class)->report(new \RuntimeException('boom'));
    }

    public function testIgnoredExceptionsAreNotSent()
    {
        $this->enableErrbit();

        $notifier = $this->createMock(Notifier::class);
        $notifier->expects($this->never())->method('notify');
        $this->app->instance(Notifier::class, $notifier);

        $this->app->make(ExceptionHandler::class)->report(new NotFoundHttpException());
    }

    public function testNothingIsSentWhenDisabled()
    {
        config([
            'services.errbit.enabled' => false,
            'services.errbit.project_key' => 'test-key',
        ]);

        $notifier = $this->createMock(Notifier::class);
        $notifier->expects($this->never())->method('notify');
        $this->app->instance(Notifier::class, $notifier);

        $this->app->make(ExceptionHandler::class)->report(new \RuntimeException('boom'));
    }

    public function testErrbitFailureDoesNotBreakErrorHandling()
    {
        $this->enableErrbit();

        $notifier = $this->createMock(Notifier::class);
        $notifier->method('notify')->willThrowException(new \Exception('errbit is down'));
        $this->app->instance(Notifier::class, $notifier);

        $this->app->make(ExceptionHandler::class)->report(new \RuntimeException('boom'));

        // Reaching this point means the notifier exception was swallowed.
        $this->assertTrue(true);
    }

    public function testNotifierIsResolvedFromConfig()
    {
        $this->enableErrbit();

        $notifier = $this->app->make(Notifier::class);

        $this->assertInstanceOf(Notifier::class, $notifier);
    }

    public function testRemoteConfigStaysDisabled()
    {
        // phpbrake 0.8.0's constructor uses empty() and silently flips
        // remoteConfig=false back to true, making it phone home to
        // airbrake.io and disable all notifications.
        $this->enableErrbit();

        $notifier = $this->app->make(Notifier::class);

        $opt = new \ReflectionProperty(Notifier::class, 'opt');
        $opt->setAccessible(true);

        $this->assertFalse($opt->getValue($notifier)['remoteConfig']);
    }

    public function testErrbitDeliveryErrorIsLogged()
    {
        // phpbrake does not throw on a failed delivery (401, rate limit,
        // unreachable host): it returns the notice with an 'error' key.
        // Swallowing that made a misconfigured Errbit indistinguishable from
        // "no errors happened" (2026-08-27: APP_ENV was unset in production).
        $this->enableErrbit();

        $notifier = $this->createMock(Notifier::class);
        $notifier->method('notify')->willReturn(['error' => 'airbrake: 401 Unauthorized']);
        $this->app->instance(Notifier::class, $notifier);

        \Log::spy();

        $this->app->make(ExceptionHandler::class)->report(new \RuntimeException('boom'));

        \Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context = []) {
            return str_contains($message, 'Errbit') && ($context['error'] ?? null) === 'airbrake: 401 Unauthorized';
        });
    }

    public function testErrbitExceptionIsLogged()
    {
        $this->enableErrbit();

        $notifier = $this->createMock(Notifier::class);
        $notifier->method('notify')->willThrowException(new \Exception('errbit is down'));
        $this->app->instance(Notifier::class, $notifier);

        \Log::spy();

        $this->app->make(ExceptionHandler::class)->report(new \RuntimeException('boom'));

        \Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context = []) {
            return str_contains($message, 'Errbit') && ($context['error'] ?? null) === 'errbit is down';
        });
    }
}
