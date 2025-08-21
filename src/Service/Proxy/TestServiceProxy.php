<?php

declare(strict_types=1);

namespace App\Service;

/**
 * AOP Proxy for App\Service\TestService
 * Generated automatically with advice injection
 * This is a complete copy of the original class with injected AOP advice
 */
class TestServiceProxy extends TestService
{
	private \Symfony\Component\DependencyInjection\ContainerInterface $container;
	private \App\AOP\JoinPoint $joinPoint;
	private \App\AOP\TargetSource $targetSource;


	public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container)
	{
		$this->container = $container;
	}


	public function getContainer(): \Symfony\Component\DependencyInjection\ContainerInterface
	{
		return $this->container;
	}


	public function getMessage(): string
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\TestService::class)) {
		    $originalService = $container->get(\App\Service\TestService::class);
		} elseif ($container->has('app.service.testservice')) {
		    $originalService = $container->get('app.service.testservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\TestService();
		}

		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'getMessage', [], [], $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->beforeTestServiceMethod($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->beforeTestServiceMethod($this->joinPoint);
		$executionArgs = $this->joinPoint->getArguments();
		$result = parent::getMessage(...$executionArgs);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->afterTestServiceMethod($this->joinPoint);

		return $result;
	}


	public function processData(string $data): string
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\TestService::class)) {
		    $originalService = $container->get(\App\Service\TestService::class);
		} elseif ($container->has('app.service.testservice')) {
		    $originalService = $container->get('app.service.testservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\TestService();
		}

		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'processData', [$data], [], $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->beforeTestServiceMethod($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->beforeTestServiceMethod($this->joinPoint);
		$executionArgs = $this->joinPoint->getArguments();
		$result = parent::processData(...$executionArgs);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\AOP\TestAspect::class);
		$aspect->afterTestServiceMethod($this->joinPoint);

		return $result;
	}
}
