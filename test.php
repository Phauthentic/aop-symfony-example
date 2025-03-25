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
            // Change the class name to a proxy name
            $node->name = new Node\Identifier($node->name->name . 'Proxy');
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() !== '__construct') {
            // Modify the method body to inject pre and post code
            $preCall = new Node\Stmt\Echo_([new Node\Scalar\String_('Before method call\n')]);
            $postCall = new Node\Stmt\Echo_([new Node\Scalar\String_('After method call\n')]);

            // Original method statements
            $originalStatements = $node->stmts;

            // Wrap the original statements between pre and post calls
            $node->stmts = array_merge([$preCall], $originalStatements, [$postCall]);
        }

        return $node;
    }
}

// Sample class code to modify
$code = <<<'CODE'
<?php
class MyClass {
    public function doSomething() {
        echo "Executing doSomething\n";
    }
}
CODE;

// Parse and modify the code
$parser = (new ParserFactory)->createForHostVersion();
$ast = $parser->parse($code);

$traverser = new NodeTraverser();
$traverser->addVisitor(new ProxyGenerator());
$modifiedAst = $traverser->traverse($ast);

// Pretty-print modified code
$prettyPrinter = new PrettyPrinter\Standard();
$generatedCode = $prettyPrinter->prettyPrintFile($modifiedAst);

echo $generatedCode;

// Save or evaluate the generated code
file_put_contents('MyClassProxy.php', $generatedCode);