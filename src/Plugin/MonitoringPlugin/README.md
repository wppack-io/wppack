# wppack/monitoring-plugin

WordPress plugin for infrastructure monitoring dashboard. Provides a real-time dashboard for AWS CloudWatch metrics with auto-discovery of configured AWS services.

## Installation

```bash
composer require wppack/monitoring-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- AWS account with CloudWatch (for production metrics)

## Features

### Dashboard

Real-time metric cards with sparkline graphs, grouped by provider. Supports configurable time ranges (1h, 3h, 12h, 24h, 7d).

### Auto-Discovery

Automatically detects AWS services from existing WpPack plugin configurations:

- **RDS / Aurora** from `DB_HOST` (detects cluster vs instance endpoints)
- **ElastiCache (Redis)** from `WPPACK_CACHE_DSN`
- **SES** from `MAILER_DSN` (ses:// scheme)
- **S3** from `STORAGE_DSN` or wp_options (via S3StoragePlugin)

### Metric Templates

Pre-configured templates for adding providers manually: RDS, Aurora Cluster, ElastiCache, CloudFront, Lambda, SQS, EC2, S3.

### Settings

DataViews-based provider management with IAM policy reference.

## Configuration

Auto-discovered providers require no additional configuration. For manual providers, configure via the Settings page in WordPress admin.

### IAM Policy

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

## License

MIT
