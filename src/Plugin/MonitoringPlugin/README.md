# wppack/monitoring-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=monitoring_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for infrastructure monitoring dashboard. Provides real-time metric cards with sparkline graphs for AWS CloudWatch and Cloudflare Analytics, with auto-discovery of configured AWS services.

## Architecture

MonitoringPlugin is a thin bootstrap layer on top of provider-agnostic monitoring components:

- **Metric collection and caching** is provided by `wppack/monitoring` (`MonitoringCollector`, `MonitoringRegistry`)
- **CloudWatch bridge** is provided by `wppack/cloudwatch-monitoring` (`CloudWatchMetricProvider`)
- **Cloudflare bridge** is provided by `wppack/cloudflare-monitoring` (`CloudflareMetricProvider`)
- **MonitoringPlugin** provides only: plugin bootstrap, admin dashboard page, auto-discovery of AWS services, metric templates, Settings UI, and DI service registration

## Installation

```bash
composer require wppack/monitoring-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- AWS account with CloudWatch (for AWS metrics)
- Cloudflare account (for Cloudflare analytics)

## Features

### Dashboard

Real-time metric cards with sparkline graphs, grouped by provider. Configurable time ranges (1h, 3h, 12h, 24h, 7d).

### Auto-Discovery

Automatically detects AWS services from existing WpPack plugin configurations:

- **RDS / Aurora** from `DB_HOST` (detects cluster vs instance endpoints)
- **ElastiCache (Redis)** from `WPPACK_CACHE_DSN`
- **SES** from `MAILER_DSN` (`ses://` scheme)
- **S3** from `STORAGE_DSN` or wp_options (via S3StoragePlugin)

### Metric Templates

Pre-configured templates for quick provider setup: RDS, Aurora Cluster, ElastiCache, CloudFront, Lambda, SQS, S3, EC2, Cloudflare Zone, Cloudflare WAF.

### Settings

DataViews-based provider management with DataForm editing. Supports template-based provider creation, IAM policy reference, and Cloudflare API token setup guide.

## Configuration

Auto-discovered providers require no additional configuration. For manual providers, configure via the Settings page in WordPress admin.

### IAM Policy

CloudWatch access requires the following IAM policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "WpPackMonitoring",
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}
```

### Cloudflare API Token

Cloudflare analytics requires an API Token with the **Zone Analytics Read** permission.

## DI Services

The plugin registers the following services into the container:

| Service | Description |
|---------|-------------|
| `MonitoringDashboardPage` | Admin dashboard page (top-level menu, position 90) |
| `MetricTemplateRegistry` | Registry for metric templates (RDS, Lambda, Cloudflare, etc.) |
| `SyncTemplatesController` | REST controller for syncing provider metrics with templates |
| `DatabaseDiscovery` | Auto-discovers RDS/Aurora from `DB_HOST` |
| `ElastiCacheDiscovery` | Auto-discovers Redis from `WPPACK_CACHE_DSN` |
| `SesDiscovery` | Auto-discovers SES from `MAILER_DSN` |
| `S3Discovery` | Auto-discovers S3 from storage configuration |

## Documentation

See [full documentation](../../docs/plugins/monitoring-plugin.md) for details.

## License

MIT
