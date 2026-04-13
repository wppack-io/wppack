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

final readonly class MysqlToken
{
    public function __construct(
        public MysqlTokenType $type,
        public string $value,
        public int $position,
    ) {}

    /**
     * Check if this token is a keyword matching the given value (case-insensitive).
     */
    public function isKeyword(string $keyword): bool
    {
        return $this->type === MysqlTokenType::Keyword
            && strcasecmp($this->value, $keyword) === 0;
    }
}
