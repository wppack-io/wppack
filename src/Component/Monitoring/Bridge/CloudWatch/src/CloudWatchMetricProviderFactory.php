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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch;

use AsyncAws\CloudWatch\CloudWatchClient;

final class CloudWatchMetricProviderFactory
{
    public function __construct(
        private readonly string $region,
    ) {}

    public static function fromEnvironment(): self
    {
        $region = \defined('WPPACK_MONITORING_AWS_REGION')
            ? (string) \constant('WPPACK_MONITORING_AWS_REGION')
            : ($_ENV['WPPACK_MONITORING_AWS_REGION'] ?? 'us-east-1');

        return new self($region);
    }

    public function create(): CloudWatchMetricProvider
    {
        $client = new CloudWatchClient(['region' => $this->region]);

        return new CloudWatchMetricProvider($client);
    }
}
