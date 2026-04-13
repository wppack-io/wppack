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

/**
 * Translates MySQL SQL queries to a target database engine's dialect.
 *
 * Used by the db.php drop-in to intercept WordPress queries (which are always MySQL)
 * and convert them for non-MySQL engines. Not needed when using Connection API directly.
 */
interface QueryTranslatorInterface
{
    /**
     * Translate a MySQL SQL query to the target engine's dialect.
     *
     * One MySQL query may expand to multiple target queries (e.g., ALTER TABLE).
     * Returns empty array if the query should be silently ignored (e.g., SET NAMES).
     *
     * @return list<string>
     */
    public function translate(string $sql): array;
}
