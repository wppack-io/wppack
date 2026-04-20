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

namespace WPPack\Plugin\MonitoringPlugin\Tests\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplate;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

#[CoversClass(MetricTemplateRegistry::class)]
#[CoversClass(MetricTemplate::class)]
final class MetricTemplateRegistryTest extends TestCase
{
    #[Test]
    public function allReturnsArrayKeyedById(): void
    {
        $all = (new MetricTemplateRegistry())->all();

        self::assertNotEmpty($all);
        self::assertContainsOnlyInstancesOf(MetricTemplate::class, $all);

        foreach ($all as $id => $template) {
            self::assertSame($id, $template->id, 'map key equals template id');
            self::assertNotSame('', $template->bridge);
            self::assertNotSame('', $template->namespace);
            self::assertNotSame('', $template->dimensionKey);
            self::assertNotEmpty($template->metrics);
        }
    }

    #[Test]
    public function allIsCachedAcrossCalls(): void
    {
        $registry = new MetricTemplateRegistry();

        $first = $registry->all();
        $second = $registry->all();

        self::assertSame($first, $second, 'second call reuses the same map');
    }

    #[Test]
    public function getReturnsTemplateById(): void
    {
        $registry = new MetricTemplateRegistry();

        $ec2 = $registry->get('ec2');

        self::assertInstanceOf(MetricTemplate::class, $ec2);
        self::assertSame('ec2', $ec2->id);
        self::assertSame('cloudwatch', $ec2->bridge);
        self::assertSame('AWS/EC2', $ec2->namespace);
        self::assertSame('InstanceId', $ec2->dimensionKey);
    }

    #[Test]
    public function getReturnsNullForUnknownId(): void
    {
        self::assertNull((new MetricTemplateRegistry())->get('no-such-template'));
    }

    #[Test]
    public function awsTemplatesUseCloudwatchBridge(): void
    {
        $registry = new MetricTemplateRegistry();

        foreach (['ec2', 'lambda', 'rds', 'dynamodb', 's3', 'sqs', 'aws-waf'] as $id) {
            $template = $registry->get($id);
            self::assertInstanceOf(MetricTemplate::class, $template, $id);
            self::assertSame('cloudwatch', $template->bridge, "{$id} uses CloudWatch bridge");
        }
    }

    #[Test]
    public function cloudflareTemplatesUseCloudflareBridge(): void
    {
        $registry = new MetricTemplateRegistry();

        foreach (['cloudflare-zone', 'cloudflare-waf'] as $id) {
            $template = $registry->get($id);
            self::assertInstanceOf(MetricTemplate::class, $template, $id);
            self::assertSame('cloudflare', $template->bridge, "{$id} uses Cloudflare bridge");
        }
    }

    #[Test]
    public function s3BucketSizeMetricUsesExtraDimensions(): void
    {
        $s3 = (new MetricTemplateRegistry())->get('s3');

        self::assertInstanceOf(MetricTemplate::class, $s3);

        $bucketSize = null;
        foreach ($s3->metrics as $metric) {
            if ($metric['metricName'] === 'BucketSizeBytes') {
                $bucketSize = $metric;
                break;
            }
        }

        self::assertNotNull($bucketSize, 'BucketSizeBytes metric exists');
        self::assertSame(86400, $bucketSize['periodSeconds']);
        self::assertSame(['StorageType' => 'StandardStorage'], $bucketSize['extraDimensions']);
    }

    #[Test]
    public function eachMetricHasRequiredKeys(): void
    {
        foreach ((new MetricTemplateRegistry())->all() as $template) {
            foreach ($template->metrics as $metric) {
                foreach (['metricName', 'label', 'description', 'stat', 'unit'] as $key) {
                    self::assertArrayHasKey($key, $metric, "{$template->id} metric missing {$key}");
                    self::assertNotSame('', $metric[$key]);
                }
            }
        }
    }
}
