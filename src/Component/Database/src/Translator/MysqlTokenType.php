<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Database\Translator;

enum MysqlTokenType
{
    case Keyword;
    case Identifier;
    case QuotedIdentifier;
    case StringLiteral;
    case NumberLiteral;
    case Operator;
    case Whitespace;
    case Comment;
    case Punctuation;
    case Placeholder;
}
