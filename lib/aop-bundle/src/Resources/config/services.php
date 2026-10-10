<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Runtime\AopRuntime;
use Phauthentic\AOP\Symfony\Command\DebugAopCommand;
use Phauthentic\AOP\Symfony\Container\AdvisorRegistryFactory;
use Phauthentic\AOP\Symfony\Container\ContainerAspectInstanceResolver;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The argument lists below are completed by AspectCollectorPass and ProxyWeavingPass.
    $services->set('phauthentic_aop.advisor_registry', AdvisorRegistry::class)
        ->factory([AdvisorRegistryFactory::class, 'create'])
        ->args([[]]);

    $services->set('phauthentic_aop.aspect_resolver', ContainerAspectInstanceResolver::class)
        ->args([new ServiceLocatorArgument([])]);

    $services->set('phauthentic_aop.parameter_binder', ParameterBinder::class);

    $services->set('phauthentic_aop.chain_factory', AdvisorChainFactory::class)
        ->args([
            service('phauthentic_aop.advisor_registry'),
            service('phauthentic_aop.aspect_resolver'),
            service('phauthentic_aop.parameter_binder'),
        ]);

    $services->set('phauthentic_aop.runtime', AopRuntime::class)
        ->args([service('phauthentic_aop.chain_factory')]);

    $services->set('phauthentic_aop.command.debug', DebugAopCommand::class)
        ->args([['advised' => [], 'skipped' => []]])
        ->tag('console.command', ['command' => 'debug:aop']);
};
