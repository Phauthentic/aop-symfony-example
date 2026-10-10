<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * Removes every `final` modifier from PHP source so a subclass proxy can extend the class and override its methods.
 * Works on tokens: `final` in strings, comments, heredocs and identifiers stays untouched.
 *
 * @throws \ParseError when the source is not valid PHP
 */
final class FinalStripper
{
    public static function strip(string $source): string
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
            $out .= $token->text;
        }

        return $out;
    }
}
