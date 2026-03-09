<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

enum TokenType
{
    case Condition;
    case And;
    case Or;
    case LeftParen;
    case RightParen;
}
