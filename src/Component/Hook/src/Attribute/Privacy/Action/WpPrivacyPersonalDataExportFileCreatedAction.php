<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Privacy\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpPrivacyPersonalDataExportFileCreatedAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_privacy_personal_data_export_file_created', $priority);
    }
}
