<?php

require 'vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class ProxyGenerator extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            // Rename the class
            $node->name = new Node\Identifier($node->name->name . 'Proxy');

            // Add properties for interceptors
            $node->stmts[] = new Node\Stmt\Property(
                Node\Stmt\Class_::MODIFIER_PRIVATE,
                [new Node\Stmt\PropertyProperty('beforeInterceptor')],
            );
            $node->stmts[] = new Node\Stmt\Property(
                Node\Stmt\Class_::MODIFIER_PRIVATE,
                [new Node\Stmt\PropertyProperty('afterInterceptor')],
            );

            // Add a constructor to inject the interceptors
            $constructor = new Node\Stmt\ClassMethod('__construct', [
                'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
                'params' => [
                    new Node\Param(new Node\Expr\Variable('beforeInterceptor')),
                    new Node\Param(new Node\Expr\Variable('afterInterceptor'))
                ],
                'stmts' => [
                    new Node\Stmt\Expression(new Node\Expr\Assign(
                        new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'beforeInterceptor'),
                        new Node\Expr\Variable('beforeInterceptor')
                    )),
                    new Node\Stmt\Expression(new Node\Expr\Assign(
                        new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'afterInterceptor'),
                        new Node\Expr\Variable('afterInterceptor')
                    )),
                ],
            ]);

            $node->stmts[] = $constructor;
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() !== '__construct') {
            // Modify method to use before and after interceptors
            $methodName = $node->name->toString();

            // Capture method arguments as an array
            $paramsArray = new Node\Expr\Array_([]);
            foreach ($node->params as $param) {
                $paramsArray->items[] = new Node\Expr\ArrayItem(new Node\Expr\Variable($param->var->name));
            }

            // Call before interceptor
            $callBefore = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('args'),
                new Node\Expr\MethodCall(
                    new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'beforeInterceptor'),
                    'before',
                    [new Node\Expr\Array_($paramsArray->items)]
                )
            ));

            // Reassign modified arguments to actual method parameters
            $assignParams = [];
            foreach ($node->params as $index => $param) {
                $assignParams[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                    new Node\Expr\Variable($param->var->name),
                    new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('args'), new Node\Scalar\LNumber($index))
                ));
            }

            // Call original method and store the result
            $callOriginal = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('result'),
                new Node\Expr\MethodCall(new Node\Expr\Variable('this'), $methodName, array_map(
                    fn($param) => new Node\Expr\Variable($param->var->name),
                    $node->params
                ))
            ));

            // Call after interceptor
            $callAfter = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('result'),
                new Node\Expr\MethodCall(
                    new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'afterInterceptor'),
                    'after',
                    [new Node\Expr\Variable('result')]
                )
            ));

            // Return modified result
            $returnStatement = new Node\Stmt\Return_(new Node\Expr\Variable('result'));

            // Replace method body
            $node->stmts = array_merge(
                [$callBefore],
                $assignParams,
                [$callOriginal, $callAfter, $returnStatement]
            );
        }

        return $node;
    }
}

// Original class
$code = <<<'CODE'
<?php
class MyClass {
    public function doSomething($name) {
        return "Hello, $name";
    }
}
CODE;

// Parse and modify
$parser = (new ParserFactory)->createForHostVersion();
$ast = $parser->parse($code);

$traverser = new NodeTraverser();
$traverser->addVisitor(new ProxyGenerator());
$modifiedAst = $traverser->traverse($ast);

// Generate and save the proxy class
$prettyPrinter = new PrettyPrinter\Standard();
$generatedCode = $prettyPrinter->prettyPrintFile($modifiedAst);

file_put_contents('MyClassProxy.php', $generatedCode);
echo "Proxy class generated: MyClassProxy.php\n";
