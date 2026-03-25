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

namespace WpPack\Component\Query\Enum;

enum TaxField: string
{
    case TermId = 'term_id';
    case Name = 'name';
    case Slug = 'slug';
    case TermTaxonomyId = 'term_taxonomy_id';
}
