<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * Removes every `final` modifier from PHP source so a subclass proxy can extend the class and override its methods.
 * Works on tokens: `final` in strings, comments, heredocs and identifiers stays untouched.
 */
final class FinalStripper
{
    /**
     * @param string|null $originalPath when given, `__DIR__` and `__FILE__` become string literals of this file's
     *                                  directory and path, so the copy behaves as if it were still the original file
     *
     * @throws \ParseError when the source is not valid PHP
     */
    public static function strip(string $source, ?string $originalPath = null): string
    {
        // TOKEN_PARSE: `final` used as a name (a method or constant called FINAL) is a T_STRING, not a T_FINAL.
        $tokens = \PhpToken::tokenize($source, \TOKEN_PARSE);
        $out = '';
        $skipWhitespace = false;
        foreach ($tokens as $token) {
            if ($token->is(\T_FINAL)) {
                $skipWhitespace = true;
                continue;
            }
            if ($skipWhitespace && $token->is(\T_WHITESPACE)) {
                $skipWhitespace = false;
                continue;
            }
            $skipWhitespace = false;
            $out .= match (true) {
                null !== $originalPath && $token->is(\T_DIR) => var_export(\dirname($originalPath), true),
                null !== $originalPath && $token->is(\T_FILE) => var_export($originalPath, true),
                default => $token->text,
            };
        }

        return $out;
    }
}
