<?php

declare(strict_types=1);

namespace App\AOP;

use InvalidArgumentException;

/**
 *
 */
class PointcutParser
{
    private string $expression;

    public function parse(string $string): array
    {
        $string = trim($string);

        $pattern = '/^([a-zA-Z_\\\\]+)::([a-zA-Z_][a-zA-Z0-9_]*)$/';
        preg_match($pattern, $string, $matches);
        var_dump($matches);

        return [];
        return [
            'type' => $matches[1],
            'args' => $this->parseArgs($matches[2])
        ];
    }
}