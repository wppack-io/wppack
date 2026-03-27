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

/**
 * Plugin Name:       WpPack Lambda
 * Description:       Lambda environment support (URL rewriting, Site Health adjustments)
 * Version:           1.0.0
 * Requires PHP:      8.2
 * Requires at least: 6.9
 * Author:            WpPack
 * License:           MIT
 */

if (getenv('LAMBDA_TASK_ROOT')) {
    add_filter('got_url_rewrite', '__return_true');

    add_filter('site_status_tests', function (array $tests): array {
        unset(
            $tests['direct']['available_updates_disk_space'],
            $tests['direct']['update_temp_backup_writable'],
            $tests['async']['background_updates'],
        );

        return $tests;
    });
}
