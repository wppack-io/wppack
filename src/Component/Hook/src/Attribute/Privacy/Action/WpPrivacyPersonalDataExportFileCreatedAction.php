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

namespace WPPack\Component\Hook\Attribute\Privacy\Action;

use WPPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpPrivacyPersonalDataExportFileCreatedAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_privacy_personal_data_export_file_created', $priority);
    }
}
