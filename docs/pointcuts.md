# Pointcut expressions

A pointcut expression selects the methods an advice applies to.
The language is a PHP flavour of AspectJ's: `\` separates namespaces, `::` separates type and method, `**` stands for any number of namespace segments.
Expressions are parsed by `Pointcut\Expression\Lexer` and `Parser` and compiled by `PointcutExpressionCompiler` into a tree of `PointcutInterface` objects.

## Grammar

```
expr       := orExpr
orExpr     := andExpr ( '||' andExpr )*
andExpr    := unary ( '&&' unary )*
unary      := '!' unary | primary
primary    := '(' expr ')' | designator | reference

reference  := name '()'                    # named pointcut in the same aspect
            | FQCN '::' name '()'          # named pointcut in another aspect class

designator := 'execution' '(' [modifier] [ '?' ] returnType [ '+' ] [ declaringType [ '+' ] '::' ] methodName '(' params ')' ')'
            | 'within'     '(' typePattern [ '+' ] ')'
            | 'this'       '(' typePattern [ '+' ] | identifier ')'
            | 'target'     '(' typePattern [ '+' ] | identifier ')'
            | 'args'       '(' bindableParams ')'
            | 'bean'       '(' beanPattern ')'
            | '@annotation' '(' typePattern [ '+' ] | identifier ')'
            | '@within'     '(' typePattern [ '+' ] | identifier ')'
            | '@target'     '(' typePattern [ '+' ] | identifier ')'
            | '@args'       '(' bindableParams ')'

modifier       := 'public' | 'protected'
params         := '' | '..' | param ( ',' param )* [ ',' '..' ]
param          := '*' | [ '?' ] typePattern [ '+' ]
bindableParams := like params, but a bare identifier may also be a binding (see below)

typePattern := segment ( '\' segment )*          # optional leading '\' is ignored
segment     := [A-Za-z0-9_*]+ | '**'              # '**' must be a whole segment
methodName  := [A-Za-z0-9_*]+
identifier  := [A-Za-z0-9_]+
beanPattern := [A-Za-z0-9_.*\-]+                  # one raw token: dots and dashes allowed
```

Precedence: `!` binds tightest, then `&&`, then `||`.
Whitespace is ignored everywhere except inside a token.
A designator keyword (`execution`, `target`, ...) is only a keyword when followed by `(`, so `execution(* target(..))` is a method named `target`.

## Designators

| Designator | Static or runtime | Matches | Binds |
|---|---|---|---|
| `execution(mod? ret Type::name(params))` | static | Methods by modifier, return type, declaring type, name and parameter types. | nothing |
| `within(Type)` | static | All methods of classes matching the type pattern. | nothing |
| `this(Type)` / `this(name)` | static / runtime | The proxy is an instance of the type. | the proxy |
| `target(Type)` / `target(name)` | static / runtime | The target is an instance of the type. | the target |
| `args(a, b, ..)` | runtime | The actual argument values, by position. | arguments |
| `bean(id)` | static | The service id (Symfony) or bean name (`ProxyFactory`). | nothing |
| `@annotation(Attr)` / `@annotation(name)` | static / runtime | The method carries the attribute. | the attribute instance |
| `@within(Attr)` / `@within(name)` | static / runtime | The target class or the class declaring the method carries the attribute. | the attribute instance |
| `@target(Attr)` / `@target(name)` | static / runtime | The target class carries the attribute. | the attribute instance |
| `@args(Attr, ..)` / `@args(name, ..)` | runtime | The runtime class of each listed argument carries the attribute. | the attribute instances |

"Static / runtime" means: static with a type pattern, runtime as soon as the subject is a binding.

### execution

```
execution([public|protected] [?]ReturnType[+] [DeclaringType[+]::]name(params))
```

- The modifier is optional; without it any visibility matches (but see [what is never advised](#never-advised)).
- `*` as return type matches any return type, including none.
  Any other return pattern is compared against the declared return type; `?` must be present to match a nullable declared type.
- The declaring type is optional.
  It matches when the target class, one of its parents, or one of its interfaces matches the pattern **and** that type declares the method.
  So `execution(* App\Service\OrderServiceInterface::placeOrder(..))` matches `OrderService::placeOrder()` because the interface declares it.
- `+` after the declaring type also accepts subtypes: `execution(* App\Contract\Repository+::*(..))`.
- `+` after the method name is a syntax error.
- The method name pattern supports `*` only: `get*`, `*`, `find*By*`.

### within

`within(App\Service\**)` matches every method of every class under `App\Service`.
With `+`, subclasses and implementors of the pattern also match: `within(App\Contract\Repository+)`.

### this and target

`this(T)` tests the proxy; `target(T)` tests the advised object.
With a subclass proxy both are the same object and `this(T)` tests the whole class hierarchy of the target.
With an interface proxy the proxy is not an instance of the target class, so `this(T)` only matches when `T` is one of the implemented interfaces.
`target(T)` tests the target's class hierarchy in both strategies.

### args

`args(..)` matches any call.
`args(int, ..)` matches when the first argument is an `int`.
`args(App\Entity\User+, *)` matches exactly two arguments, the first an instance of `User` or a subclass.
`args(?string)` matches one argument that is a string or `null`.

Argument matching uses `instanceof` for class patterns and `get_debug_type()` for builtins (`int`, `string`, `float`, `bool`, `array`, `null`, `mixed`, `object`).
At weave time the static half prunes methods whose arity or declared types can never match, for example `args(int)` against `foo(string $s)`.
`int` is compatible with a declared `float`, and `bool` with declared `true`/`false`.

Proxies forward every declared parameter, including optional ones with their default values.
So `args(name)` does not match `greet(string $name, int $times = 1)`; use `args(name, ..)` or `args(name, *)`.
Variadic parameters arrive with their original keys, so named variadic arguments keep their names in `getArgs()`.

### bean

`bean(App\Service\StringService)` matches the service whose id is that class name (the default for autowired services).
`bean(app.*_service)` uses `*` as a glob over the id.
Dots, dashes and backslashes are allowed inside `bean()` and nothing else is parsed there.
Outside Symfony the bean name is the one passed to `ProxyFactory::createProxyClass()` or `createProxy()`; without one, `bean()` never matches.

### @annotation, @within, @target, @args

These test PHP attributes.
`@annotation(App\Attribute\Retry)` matches methods carrying `#[Retry]`.
With `+`, subclasses of the attribute class also match.
`@within(Attr)` looks at the target class and at the class that declares the method, so attributes on a parent class count.
`@target(Attr)` looks at the target class only.
`@args(Attr, ..)` looks at the class of each listed argument object; a non-object argument never matches.

## Type patterns

| Pattern | Matches | Does not match |
|---|---|---|
| `App\Service\UserService` | `App\Service\UserService` | `App\Service\UserServiceX`, `App\Service\Sub\UserService` |
| `App\Service\*Service` | `App\Service\UserService` | `App\Service\Sub\UserService` |
| `App\Service\*` | `App\Service\X` | `App\Service`, `App\Service\Sub\X` |
| `App\Service\**` | `App\Service\X`, `App\Service\Sub\Y` | `App\ServiceX\Y`, `App\Other\Y` |
| `App\**\Foo` | `App\Foo`, `App\A\B\Foo` | `App\A\Bar` |
| `**\Foo` | `Foo`, `A\B\Foo` | `A\Foo\B` |
| `**` | anything | |
| `*` | any single segment name, including builtins like `int` | `App\Foo` |
| `*Repository` | `UserRepository` | `App\UserRepository` |
| `\App\Foo` | `App\Foo` (leading backslash ignored) | |
| `App\Foo+` | `App\Foo`, its subclasses, and implementors if it is an interface | its parent classes |
| `Se**`, `**Service` | syntax error: `**` must be a whole segment | |

Regex metacharacters other than `*` are literal: `App\Foo.Bar` does not match `App\FooXBar`.

## Parameter patterns in execution()

| Pattern | Matches |
|---|---|
| `()` | No parameters. |
| `(..)` | Any number of parameters, including none. |
| `(*)` | Exactly one parameter of any type. |
| `(*, *)` | Exactly two parameters. |
| `(int, ..)` | First parameter declared `int`, any number after it. |
| `(?int)` | One parameter declared `?int` (or `int|null`). |
| `(App\Entity\User+)` | One parameter whose declared type is `User` or a subtype. |
| `(string, *, array)` | Three parameters, first `string`, third `array`. |

Rules:

- `..` may only appear last, after at least one parameter or alone.
- A type pattern is compared with the declared parameter type.
  Untyped, union and intersection parameters match only `*`.
- A nullable declared type matches only a `?`-prefixed pattern; `(int)` does not match `?int $x`.
- A `?` pattern does not match a non-nullable declared type.
- Bare identifiers inside `execution()` are type names: `execution(* *(Foo))` matches a parameter declared `Foo`.

## Named pointcuts

A method marked `#[Pointcut('...')]` must be public, non-static, parameterless and return `void`.
Reference it as `name()` from the same aspect or as `Fully\Qualified\Aspect::name()` from any aspect.

```php
#[Aspect]
final class CommonPointcuts
{
    #[Pointcut('execution(* App\Service\**::*(..))')]
    public function serviceLayer(): void
    {
    }
}

#[Aspect]
final class LoggingAspect
{
    #[Before('App\Aspect\CommonPointcuts::serviceLayer() && !execution(* **::reverse(..))')]
    public function before(JoinPointInterface $jp): void { /* ... */ }
}
```

Named pointcuts can reference other named pointcuts.
A cycle (`a() -> b() -> a()`) raises `PointcutSyntaxException` with the chain in the message.
The referenced class must be an `#[Aspect]`; with the bundle it does not need to be a registered service, the resolver reflects it on demand.

## Binding

A bare identifier inside `args`, `@args`, `this`, `target`, `@annotation`, `@within` or `@target` is a binding when the advice method has a parameter with that name.
Otherwise it is read as a type pattern, which is why `within(Foo)` is a type and `this(proxy)` with a `$proxy` parameter is a binding.

The parameter type restricts the match:

```php
#[Around('@annotation(retry)')]
public function around(ProceedingJoinPointInterface $pjp, Retry $retry): mixed
```

Only methods carrying `#[Retry]` (or a subclass of it) match.
A nullable parameter type (`?Retry $retry`) restricts like its non-nullable form; the bound value is never null when the pointcut matches.
A parameter typed `mixed`, `object`, or untyped does not restrict: `@annotation(attr)` with `mixed $attr` binds the first attribute of any kind on the method.

For `args`, the parameter type is the runtime check: `args(id, ..)` with `int $id` matches only when the first argument is an `int`.

Rules enforced at compile time, each raising `BindingException`:

- Every branch of `||` must bind the same names.
- A negated pointcut cannot bind: `!target(t)` with a `$t` parameter is an error.
- Every advice parameter must be a join point, the `returning`/`throwing` parameter, or bound by the pointcut (see [Advice](advice.md#parameters)).

## Errors

| Exception | Raised when |
|---|---|
| `PointcutSyntaxException` | The expression cannot be tokenised or parsed. The message includes the position: `Unexpected ")" at position 15 in pointcut "..."`. Also for unknown or cyclic named pointcuts and a missing resolver. |
| `UnsupportedPointcutDesignatorException` | The expression uses `call`, `cflow`, `cflowbelow`, `get`, `set`, `withincode`, `if`, `initialization`, `preinitialization`, `staticinitialization`, `handler` or `adviceexecution`, or any other `word(...)` with content that is not a reference. |
| `BindingException` | A binding rule above is violated, or an advice parameter cannot be bound. |

All three extend `AopException`.
With the bundle they are wrapped in an `AspectDefinitionException` whose message names the aspect class and method: `Invalid advice App\Aspect\X::y(): ...`.

## Examples

| Expression | Selects |
|---|---|
| `execution(* App\Service\**::*(..))` | Every method of every class under `App\Service`. |
| `execution(public * App\Service\*Service::find*(..))` | Public `find...` methods of classes named `...Service` directly in `App\Service`. |
| `execution(?string App\**::*(int, ..))` | Methods returning `?string` whose first parameter is `int`. |
| `execution(App\Entity\User+ *(..))` | Methods declared to return `User` or a subclass, in any class. |
| `execution(* App\Contract\RepositoryInterface+::*(..))` | Methods declared by `RepositoryInterface` on any implementor. |
| `within(App\Service\**) && !@annotation(App\Attribute\NoLog)` | Service methods not marked `#[NoLog]`. |
| `@annotation(App\Attribute\Transactional)` | Methods marked `#[Transactional]`. |
| `@within(App\Attribute\Audited)` | Methods of classes marked `#[Audited]`, including inherited methods. |
| `bean(App\Service\StringService) && args(name, ..)` | One service, binding its first argument to `$name`. |
| `(args(id) && within(A)) \|\| (args(id, ..) && within(B))` | Allowed: both branches bind `id`. |
| `execution(* **::reverse(..)) \|\| args(id)` | Error: branches bind different names. |

## Never advised

Private, static, abstract and magic methods (`__*`), constructors and destructors, methods returning by reference, and methods with an object default value (`new` in initializer).
Under the subclass strategy, final methods are also skipped.
Under the interface strategy, only public methods declared by an interface of the target are advised.
Enums, internal classes, abstract classes, anonymous classes, interfaces and traits are never proxied.
`bin/console debug:aop` reports skipped services with the reason; see [Proxies](proxies.md#eligibility) for the full list.
