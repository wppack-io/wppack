# wppack/cloudwatch-monitoring

AWS CloudWatch bridge for the WpPack Monitoring component. Queries CloudWatch `GetMetricData` API for metric retrieval.

## Installation

```bash
composer require wppack/cloudwatch-monitoring
```

## Requirements

- PHP 8.2+
- `wppack/monitoring` ^1.0
- `async-aws/cloud-watch` ^1.0

## Usage

The bridge is auto-registered via DI when the class is available. No manual configuration needed.

```php
use WpPack\Component\Monitoring\Bridge\CloudWatch\CloudWatchMetricProvider;

// Registered automatically by MonitoringServiceProvider
$provider = new CloudWatchMetricProvider();
$results = $provider->query($monitoringProvider, $timeRange);
```

## Features

- Automatic `Period` adjustment based on time range (5min for ≤1d, 15min for ≤3d, 1h for >3d)
- Per-region CloudWatch client caching
- Optional IAM credentials (falls back to instance role / environment)

## IAM Policy

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}
```

## License

MIT
