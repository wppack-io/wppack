# wppack/cloudwatch-monitoring

AWS CloudWatch bridge for WPPack Monitoring component. Implements `MetricProviderInterface` to query CloudWatch metrics via the GetMetricData API.

## Installation

```bash
composer require wppack/cloudwatch-monitoring
```

## Requirements

- PHP 8.2+
- `wppack/monitoring` ^1.0
- `async-aws/cloud-watch` ^1.0

## Usage

CloudWatchMetricProvider implements MetricProviderInterface:
- getName() returns 'cloudwatch'
- isAvailable() always returns true
- query() calls CloudWatch GetMetricData and returns MetricResult[]

Supports per-provider credentials (accessKeyId/secretAccessKey) or IAM role fallback. Maintains per-region client cache.

## Adaptive Period Resolution

- ≤6 hours: 60s (1 min)
- ≤1 day: 300s (5 min)
- ≤3 days: 900s (15 min)
- >3 days: 3600s (1 hour)

## IAM Policy

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": "cloudwatch:GetMetricData",
    "Resource": "*"
  }]
}
```

## License

MIT
