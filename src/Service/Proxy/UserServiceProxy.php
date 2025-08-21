<?php

declare(strict_types=1);

namespace App\Service;

/**
 * AOP Proxy for App\Service\UserService
 * Generated automatically with advice injection
 * This is a complete copy of the original class with injected AOP advice
 */
class UserServiceProxy extends UserService
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


	public function tellName(string $test): void
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\UserService::class)) {
		    $originalService = $container->get(\App\Service\UserService::class);
		} elseif ($container->has('app.service.userservice')) {
		    $originalService = $container->get('app.service.userservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\UserService();
		}

		// Create interceptor chain for Around advice
		$interceptors = [function($joinPoint) { $aspect = $joinPoint->getAspect(\App\Aspects\LoggingAspect::class); return $aspect->aroundTellNameMethod($joinPoint); }, function($joinPoint) { $aspect = $joinPoint->getAspect(\App\Aspects\PerformanceAspect::class); return $aspect->measureExecutionTime($joinPoint); }, function($joinPoint) { $aspect = $joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class); return $aspect->profileUserServiceMethods($joinPoint); }];
		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'tellName', [$test], $interceptors, $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\ArgumentModificationAspect::class);
		$aspect->modifyArguments($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\LoggingAspect::class);
		$aspect->beforeUserServiceMethod($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\Aspects\ArgumentModificationAspect::class);
		$aspect->modifyArguments($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\LoggingAspect::class);
		$aspect->beforeUserServiceMethod($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		// Check if Before advice requested early return
		if ($this->joinPoint->shouldReturnEarly()) {
		    $earlyReturnValue = $this->joinPoint->getEarlyReturnValue();
		    // Execute After advice even for early returns
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\ArgumentModificationAspect::class);
		    $aspect->logFinalState($this->joinPoint);
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\LoggingAspect::class);
		    $aspect->afterUserServiceMethod($this->joinPoint);
		    return;
		}

		$executionArgs = $this->joinPoint->getArguments();
		try {
		    $result = $this->joinPoint->proceed();
		} catch (\Throwable $exception) {
		    // Re-throw the exception after advice execution
		    throw $exception;
		}

		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\Aspects\ArgumentModificationAspect::class);
		$aspect->logFinalState($this->joinPoint);

		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\Aspects\LoggingAspect::class);
		$aspect->afterUserServiceMethod($this->joinPoint);
	}
}
