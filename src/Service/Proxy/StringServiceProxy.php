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
		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->checkCache($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->checkCache($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		// Check if Before advice requested early return
		if ($this->joinPoint->shouldReturnEarly()) {
		    $earlyReturnValue = $this->joinPoint->getEarlyReturnValue();
		    // Execute After advice even for early returns
		    return $earlyReturnValue;
		}

		$executionArgs = $this->joinPoint->getArguments();
		try {
		    if ($this->joinPoint->shouldSkipMethodExecution()) {
		        // Method execution was skipped, use null as result
		        $result = null;
		    } else {
		        // Call the parent method with modified arguments from JoinPoint
		        $result = parent::processString(...$executionArgs);
		    }
		} catch (\Throwable $exception) {
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		    $aspect->handleStringServiceException($exception);
		    // Re-throw the exception after advice execution
		    throw $exception;
		}

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$result = $aspect->enhanceResults($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
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
		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->checkRateLimit($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->checkRateLimit($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		// Check if Before advice requested early return
		if ($this->joinPoint->shouldReturnEarly()) {
		    $earlyReturnValue = $this->joinPoint->getEarlyReturnValue();
		    // Execute After advice even for early returns
		    return $earlyReturnValue;
		}

		$executionArgs = $this->joinPoint->getArguments();
		try {
		    if ($this->joinPoint->shouldSkipMethodExecution()) {
		        // Method execution was skipped, use null as result
		        $result = null;
		    } else {
		        // Call the parent method with modified arguments from JoinPoint
		        $result = parent::reverseString(...$executionArgs);
		    }
		} catch (\Throwable $exception) {
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		    $aspect->handleStringServiceException($exception);
		    // Re-throw the exception after advice execution
		    throw $exception;
		}

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$result = $aspect->enhanceResults($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
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
		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->validateInput($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$aspect->validateInput($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		// Check if Before advice requested early return
		if ($this->joinPoint->shouldReturnEarly()) {
		    $earlyReturnValue = $this->joinPoint->getEarlyReturnValue();
		    // Execute After advice even for early returns
		    return $earlyReturnValue;
		}

		$executionArgs = $this->joinPoint->getArguments();
		try {
		    if ($this->joinPoint->shouldSkipMethodExecution()) {
		        // Method execution was skipped, use null as result
		        $result = null;
		    } else {
		        // Call the parent method with modified arguments from JoinPoint
		        $result = parent::uppercaseString(...$executionArgs);
		    }
		} catch (\Throwable $exception) {
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		    $aspect->handleStringServiceException($exception);
		    // Re-throw the exception after advice execution
		    throw $exception;
		}

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$result = $aspect->enhanceResults($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$result = $aspect->addAfterSuffix($result);
		return $result;
	}


	public function riskyStringOperation(string $input): string
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

		// Create interceptor chain for Around advice
		$interceptors = [function($joinPoint) { $aspect = $joinPoint->getAspect(\App\Aspects\FlowControlAspect::class); return $aspect->aroundRiskyOperation($joinPoint); }];
		$this->joinPoint = new \App\AOP\JoinPoint($originalService, 'riskyStringOperation', [$input], $interceptors, $this, $container);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);

		// Execute Before advice
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);

		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->logServiceMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$aspect->validateSetterMethods($this->joinPoint);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$aspect->addBeforePrefix($this->joinPoint);
		// Check if Before advice requested early return
		if ($this->joinPoint->shouldReturnEarly()) {
		    $earlyReturnValue = $this->joinPoint->getEarlyReturnValue();
		    // Execute After advice even for early returns
		    return $earlyReturnValue;
		}

		$executionArgs = $this->joinPoint->getArguments();
		try {
		    $result = $this->joinPoint->proceed();
		} catch (\Throwable $exception) {
		    $aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		    $aspect->handleStringServiceException($exception);
		    // Re-throw the exception after advice execution
		    throw $exception;
		}

		$aspect = $this->joinPoint->getAspect(\App\Aspects\FlowControlAspect::class);
		$result = $aspect->enhanceResults($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\SpringStyleAspect::class);
		$result = $aspect->logGetterMethods($result);
		$aspect = $this->joinPoint->getAspect(\App\Aspects\StringModificationAspect::class);
		$result = $aspect->addAfterSuffix($result);
		return $result;
	}
}
