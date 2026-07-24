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
}
