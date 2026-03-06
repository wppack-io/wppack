<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Enum;

enum TaxField: string
{
    case TermId = 'term_id';
    case Name = 'name';
    case Slug = 'slug';
    case TermTaxonomyId = 'term_taxonomy_id';
}
