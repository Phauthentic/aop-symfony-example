<?php

declare(strict_types=1);

namespace App\Service;

/**
 * AOP Proxy for App\Service\StringService
 * Generated automatically with advice injection
 * This is a complete copy of the original class with injected AOP advice
 */
class StringServiceProxy extends StringService
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


	public function processString(string $input): string
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\StringService::class)) {
		    $originalService = $container->get(\App\Service\StringService::class);
		} elseif ($container->has('app.service.stringservice')) {
		    $originalService = $container->get('app.service.stringservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\StringService();
		}

		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'processString', [$input], [], $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		$executionArgs = $this->joinPoint->getArguments();
		$result = parent::processString(...$executionArgs);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$result = $aspect->addAfterSuffix($result);
		return $result;
	}


	public function reverseString(string $input): string
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\StringService::class)) {
		    $originalService = $container->get(\App\Service\StringService::class);
		} elseif ($container->has('app.service.stringservice')) {
		    $originalService = $container->get('app.service.stringservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\StringService();
		}

		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'reverseString', [$input], [], $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		$executionArgs = $this->joinPoint->getArguments();
		$result = parent::reverseString(...$executionArgs);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$result = $aspect->addAfterSuffix($result);
		return $result;
	}


	public function uppercaseString(string $input): string
	{
		// Get the original service instance from container
		$container = $this->getContainer();
		// Try to get by class name first, then by common service ID patterns
		$originalService = null;
		if ($container->has(\App\Service\StringService::class)) {
		    $originalService = $container->get(\App\Service\StringService::class);
		} elseif ($container->has('app.service.stringservice')) {
		    $originalService = $container->get('app.service.stringservice');
		} else {
		    // Fallback: create new instance
		    $originalService = new \App\Service\StringService();
		}

		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'uppercaseString', [$input], [], $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		$executionArgs = $this->joinPoint->getArguments();
		$result = parent::uppercaseString(...$executionArgs);

		$aspect = $this->joinPoint->getAspect(\App\AOP\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\AOP\StringModificationAspect::class);
		$result = $aspect->addAfterSuffix($result);
		return $result;
	}
}
