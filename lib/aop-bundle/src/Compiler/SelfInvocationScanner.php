<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Compiler;

/**
 * Finds `$this->method(` calls to advised methods in a target class' file. With an interface proxy `$this` is the
 * plain target, so those calls bypass advice. A token scan, so it is cheap and conservative: it cannot see calls
 * through other variables or dynamic names.
 */
final class SelfInvocationScanner
{
    /**
     * @param \ReflectionClass<object> $target
     * @param list<string>             $advisedMethods
     *
     * @return list<array{caller: string, method: string, file: string, line: int}>
     */
    public static function scan(\ReflectionClass $target, array $advisedMethods): array
    {
        $file = $target->getFileName();
        if (false === $file || !is_file($file) || [] === $advisedMethods) {
            return [];
        }
        $source = @file_get_contents($file);
        if (false === $source) {
            return [];
        }

        $advised = array_change_key_case(array_fill_keys($advisedMethods, true));
        $tokens = array_values(array_filter(
            \PhpToken::tokenize($source),
            static fn (\PhpToken $t): bool => !$t->isIgnorable(),
        ));
        $found = [];
        $class = $caller = null;
        foreach ($tokens as $i => $token) {
            $next = $tokens[$i + 1] ?? null;
            if ($token->is(\T_CLASS) && $next?->is(\T_STRING) && !($tokens[$i - 1] ?? null)?->is(\T_DOUBLE_COLON)) {
                $class = $next->text;
            } elseif ($token->is(\T_FUNCTION) && $next?->is(\T_STRING)) {
                $caller = $next->text;
            } elseif ($token->is(\T_VARIABLE) && '$this' === $token->text && $class === $target->getShortName() && null !== $caller
                && ($next?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR]) ?? false)
                && ($tokens[$i + 2] ?? null)?->is(\T_STRING) && ($tokens[$i + 3] ?? null)?->is('(')
                && isset($advised[strtolower($tokens[$i + 2]->text)])) {
                $found[] = ['caller' => $caller, 'method' => $tokens[$i + 2]->text, 'file' => $file, 'line' => $token->line];
            }
        }

        return $found;
    }
}
