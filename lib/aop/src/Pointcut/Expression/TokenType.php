<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Expression;

enum TokenType
{
    case LParen;
    case RParen;
    case Comma;
    case DoubleColon;
    case Ellipsis;
    case And;
    case Or;
    case Not;
    case Plus;
    case Question;
    /** Designator name directly followed by `(`. */
    case Keyword;
    /** Pattern or identifier: [A-Za-z0-9_*\\]+, or the raw token inside bean(). */
    case Word;
    case Eof;
}
