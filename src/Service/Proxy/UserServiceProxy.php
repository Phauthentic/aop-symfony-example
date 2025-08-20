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
		$interceptors = [function($joinPoint) { $aspect = $joinPoint->getAspect(\App\AOP\LoggingAspect::class); return $aspect->aroundTellNameMethod($joinPoint); }, function($joinPoint) { $aspect = $joinPoint->getAspect(\App\AOP\PerformanceAspect::class); return $aspect->measureExecutionTime($joinPoint); }, function($joinPoint) { $aspect = $joinPoint->getAspect(\App\AOP\SpringStyleAspect::class); return $aspect->profileUserServiceMethods($joinPoint); }];
		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'tellName', [$test], $interceptors, $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\ArgumentModificationAspect::class);
		$aspect->modifyArguments($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\LoggingAspect::class);
		$aspect->beforeUserServiceMethod($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute method through Around advice interceptor chain
		$result = $this->joinPoint->proceed();

		// Execute AfterReturning advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\ArgumentModificationAspect::class);
		$aspect->modifyResult($result);

		// Execute AfterReturning advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\LoggingAspect::class);
		$aspect->afterReturningTellNameMethod($result);

		// Execute AfterReturning advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logGetterMethods($result);

		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\AOP\ArgumentModificationAspect::class);
		$aspect->logFinalState($this->joinPoint);

		// Execute After advice (finally)
		$aspect = $this->joinPoint->getAspect(\App\AOP\LoggingAspect::class);
		$aspect->afterUserServiceMethod($this->joinPoint);
	}
}
