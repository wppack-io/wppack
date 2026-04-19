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
 * Raised by translators when the source SQL uses a MySQL-only feature that
 * has no safe rewrite on the target engine (FULLTEXT MATCH AGAINST,
 * SUBSTRING_INDEX with negative counts on SQLite, unimplemented JSON path
 * shapes, …). Distinct from ParserFailureException: the parser succeeded,
 * we just can't produce correct target SQL.
 *
 * Subclass of TranslationException so existing catch(TranslationException)
 * blocks keep working while callers who care can dispatch on the subtype.
 */
final class UnsupportedFeatureException extends TranslationException {}
