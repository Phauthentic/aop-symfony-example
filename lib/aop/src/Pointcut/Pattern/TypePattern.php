<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Pattern;

use Phauthentic\AOP\Pointcut\Expression\Ast\TypePatternNode;

/**
 * Type name pattern: `*` within a segment, `**` as a whole segment for any number of segments,
 * trailing `+` (includeSubtypes) for the type or any subtype. Compiled to one regex.
 */
final class TypePattern
{
    private readonly string $regex;

    public function __construct(
        string $pattern,
        public readonly bool $nullable = false,
        public readonly bool $includeSubtypes = false,
    ) {
        $this->regex = self::compile(ltrim($pattern, '\\'));
    }

    public static function fromNode(TypePatternNode $node): self
    {
        return new self($node->pattern, $node->nullable, $node->includeSubtypes);
    }

    /**
     * Exact match of the pattern against a name, ignoring `+`.
     */
    public function matches(string $fqcn): bool
    {
        return 1 === preg_match($this->regex, ltrim($fqcn, '\\'));
    }

    /**
     * Like matches(), but with `+` the name may also be a class/interface that has a matching ancestor.
     */
    public function matchesName(string $name): bool
    {
        if ($this->matches($name)) {
            return true;
        }

        return $this->includeSubtypes
            && (class_exists($name) || interface_exists($name))
            && $this->matchesType(new \ReflectionClass($name));
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    public function matchesType(\ReflectionClass $class): bool
    {
        if ($this->matches($class->getName())) {
            return true;
        }
        if (!$this->includeSubtypes) {
            return false;
        }
        for ($parent = $class->getParentClass(); false !== $parent; $parent = $parent->getParentClass()) {
            if ($this->matches($parent->getName())) {
                return true;
            }
        }
        foreach ($class->getInterfaceNames() as $interface) {
            if ($this->matches($interface)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a declared parameter/return type. Untyped, union and intersection types never match.
     * A nullable declared type (`?T`) matches only a nullable pattern (`?T`), never the plain `T`.
     */
    public function matchesDeclared(?\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }
        $name = $type->getName();
        $nullable = $type->allowsNull() && !in_array($name, ['mixed', 'null'], true);

        return $nullable === $this->nullable && $this->matchesName($name);
    }

    private static function compile(string $pattern): string
    {
        $segments = explode('\\', $pattern);
        $last = count($segments) - 1;
        $regex = '';
        foreach ($segments as $i => $segment) {
            if ('**' === $segment) {
                $regex .= $i === $last ? '.+' : '(?:[^\\\\]+\\\\)*';
                continue;
            }
            $regex .= '*' === $segment ? '[^\\\\]+' : str_replace('\*', '[^\\\\]*', preg_quote($segment, '/'));
            if ($i !== $last) {
                $regex .= '\\\\';
            }
        }

        return '/^'.$regex.'$/D';
    }
}
