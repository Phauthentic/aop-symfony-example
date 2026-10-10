<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Container;

use Phauthentic\AOP\Advisor\AdvisorFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Advisor\MetadataNamedPointcutResolver;
use Phauthentic\AOP\Aspect\AspectMetadata;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Exception\AopException;
use Phauthentic\AOP\Exception\AspectDefinitionException;

/**
 * Builds the registry from aspect class names by reflecting their metadata, so compiled pointcuts are never
 * serialised into the dumped container. Used at compile time (weaving) and at runtime, hence identical matches.
 */
final class AdvisorRegistryFactory
{
    /**
     * Also validates every pointcut, parameter binding and introduction.
     *
     * @param list<string> $aspectClasses
     *
     * @throws AspectDefinitionException naming the aspect class and advice method
     */
    public static function create(array $aspectClasses): AdvisorRegistry
    {
        $reader = new AspectMetadataReader();
        $metadata = array_map(
            static fn (string $class): AspectMetadata => class_exists($class)
                ? $reader->read($class)
                : throw new AspectDefinitionException(sprintf('Aspect class "%s" does not exist.', $class)),
            $aspectClasses,
        );
        $pointcuts = new MetadataNamedPointcutResolver($metadata, $reader);
        $factory = new AdvisorFactory();
        $binder = new ParameterBinder();

        $advisors = [];
        $introductions = [];
        foreach ($metadata as $meta) {
            array_push($introductions, ...$factory->introductionsFromMetadata($meta));
            foreach ($meta->advices as $advice) {
                try {
                    // One advice per call, so a failure can name its method.
                    $single = new AspectMetadata($meta->aspectClass, $meta->order, $meta->namedPointcuts, [$advice]);
                    foreach ($factory->fromMetadata($single, $pointcuts) as $advisor) {
                        $binder->plan(new \ReflectionMethod($advice->aspectClass, $advice->methodName), $advice, $advisor->pointcut);
                        $advisors[] = $advisor;
                    }
                } catch (AopException $e) {
                    throw new AspectDefinitionException(sprintf('Invalid advice %s::%s(): %s', $advice->aspectClass, $advice->methodName, $e->getMessage()), 0, $e);
                }
            }
        }

        return new AdvisorRegistry($advisors, $introductions);
    }
}
