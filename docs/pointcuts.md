# Pointcut expressions

Pointcuts select the methods an advice applies to.
The language is a PHP flavour of AspectJ.
Differences from Java are deliberate: `\` separates namespaces, `::` separates type and method, and `**` stands for any number of namespace segments.

## Grammar

```
expr      := orExpr
orExpr    := andExpr ( '||' andExpr )*
andExpr   := unary ( '&&' unary )*
unary     := '!' unary | primary
primary   := '(' expr ')' | designator | reference
reference := name '()'                 # named pointcut in the same aspect
           | FQCN '::' name '()'       # named pointcut in another aspect class
```

## Designators

| Designator | Matches | Binds |
|---|---|---|
| `execution([public\|protected] return Type::name(params))` | Methods by modifier, return type, declaring type (or an ancestor or interface declaring the method), name and parameter types | nothing |
| `within(Type)` | Methods of classes matching the type pattern | nothing |
| `this(Type\|name)` | The proxy is an instance of the type | the proxy |
| `target(Type\|name)` | The target is an instance of the type | the target |
| `args(Type, name, ..)` | Runtime argument types, by position | arguments |
| `@annotation(Attr\|name)` | The method carries the PHP attribute | the attribute instance |
| `@within(Attr\|name)` | The target class or the class declaring the method carries the attribute | the attribute instance |
| `@target(Attr\|name)` | The target class carries the attribute | the attribute instance |
| `@args(Attr\|name)` | The runtime class of an argument carries the attribute | the attribute instance |
| `bean(service.id)` | Symfony service id (dotted or an autowired FQCN such as `App\Service\Mailer`), `*` wildcard | nothing |

Unsupported AspectJ designators (`call`, `cflow`, `get`, `set`, `withincode`, `if`) raise an `UnsupportedPointcutDesignatorException`.
Other syntax errors raise a `PointcutSyntaxException` with the position.

## Type patterns

- `*` inside a segment matches any characters except `\`, for example `App\Service\*Service`.
- `**` as a whole segment matches zero or more namespace segments, for example `App\Service\**`.
- A trailing `+` matches the type and all of its subtypes.
- A leading `\` is ignored.
- `Se**` or `**Service` is a syntax error.

## Parameter patterns

- `()` matches no parameters.
- `(..)` matches any parameters.
- `*` matches exactly one parameter of any type.
- A type pattern matches the declared parameter type.
- `..` may only appear last, after at least one parameter or alone.
- Nullable declared types match only a pattern prefixed with `?`.
- Union and intersection types match only `*`.

## Combining and naming

```php
#[Aspect]
final class CommonPointcuts
{
    #[Pointcut('execution(* App\Service\**::*(..))')]
    public function serviceLayer(): void {}
}

#[Before('App\Aspect\CommonPointcuts::serviceLayer() && !execution(* **::reverse(..))')]
```

## Argument binding

An identifier inside `args`, `this`, `target`, `@annotation`, `@within`, `@target` or `@args` is a binding when it equals the name of an advice parameter.
Otherwise it is read as a type pattern.
The parameter type restricts the match at runtime.
A nullable parameter type (`?Retry $retry`) restricts the match like its non-nullable form.
Every branch of `||` must bind the same names, otherwise compiling the pointcut raises a `BindingException`.

`args` sees every declared parameter: proxies forward optional parameters with their default values.
So `args(name)` does not match `greet(string $name, int $times = 1)`; use `args(name, ..)` or `args(name, *)`.

```php
#[Before('execution(* App\Service\StringService::greet(..)) && args(name, ..)')]
public function check(string $name): void { /* ... */ }

#[Around('@annotation(retry)')]
public function around(ProceedingJoinPointInterface $pjp, Retry $retry): mixed { /* ... */ }
```

## Never advised

Private, static, final (subclass strategy), abstract and magic methods, constructors, destructors, and methods with by-reference parameters or return values.
Enums, internal classes, abstract classes, interfaces and traits are never proxied.
`bin/console debug:aop` reports skipped services with the reason.
