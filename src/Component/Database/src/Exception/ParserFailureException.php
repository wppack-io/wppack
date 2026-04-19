<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Database\Exception;

/**
 * Raised by translators when phpmyadmin/sql-parser could not produce any
 * statement for the input SQL — typically because the input is malformed
 * or uses vendor-specific syntax we have never parsed. Distinct from
 * UnsupportedFeatureException: here we couldn't even understand the
 * shape of the query.
 *
 * Subclass of TranslationException so existing catch blocks keep working
 * while callers who care can distinguish parse failures from feature
 * gaps (e.g. surface parse failures as HTTP 500, feature gaps as HTTP
 * 501 Not Implemented).
 */
final class ParserFailureException extends TranslationException {}
