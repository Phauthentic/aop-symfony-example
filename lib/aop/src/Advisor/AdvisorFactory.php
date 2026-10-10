<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advisor;

use Phauthentic\AOP\Aspect\AspectMetadata;
use Phauthentic\AOP\Aspect\IntroductionDefinition;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Pointcut\Expression\NamedPointcutResolverInterface;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Pointcut\Pattern\TypePattern;

final class AdvisorFactory
{
    public function __construct(private readonly PointcutExpressionCompiler $compiler = new PointcutExpressionCompiler())
    {
    }

    /**
     * @return list<Advisor>
     */
    public function fromMetadata(AspectMetadata $meta, NamedPointcutResolverInterface $resolver): array
    {
        $advisors = [];
        foreach ($meta->advices as $advice) {
            $method = new \ReflectionMethod($advice->aspectClass, $advice->methodName);
            $pointcut = $this->compiler->compile($advice->expression, ParameterBinder::bindingCandidates($method, $advice), $meta->aspectClass, $resolver);
            $advisors[] = new Advisor($advice->aspectClass.'::'.$advice->methodName, $advice, $meta->order, $pointcut);
        }

        return $advisors;
    }

    /**
     * @return list<IntroductionAdvisor>
     */
    public function introductionsFromMetadata(AspectMetadata $meta): array
    {
        return array_map(
            static fn (IntroductionDefinition $i): IntroductionAdvisor => new IntroductionAdvisor(
                $meta->aspectClass,
                new TypePattern(rtrim($i->typePattern, '+'), false, str_ends_with($i->typePattern, '+')),
                $i->interface,
                $i->defaultImpl,
                $meta->order,
            ),
            $meta->introductions,
        );
    }
}
