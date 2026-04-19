# wppack/cloudflare-monitoring

Cloudflare Analytics bridge for WPPack Monitoring component. Implements `MetricProviderInterface` to query Cloudflare analytics via the GraphQL API.

## Installation

```bash
composer require wppack/cloudflare-monitoring
```

## Requirements

- PHP 8.2+
- `wppack/monitoring` ^1.0

No external Cloudflare SDK required; uses WordPress `wp_remote_post()` for API calls.

## Usage

CloudflareMetricProvider implements MetricProviderInterface:
- getName() returns 'cloudflare'
- isAvailable() always returns true
- query() calls Cloudflare GraphQL Analytics API

Supports zone analytics (CDN metrics) and WAF/firewall events.

## Supported Metrics

### Zone Analytics

requests, cachedRequests, cacheRate (computed), bandwidth, cachedBandwidth, threats, pageViews, uniques, status2xx/3xx/4xx/5xx

### WAF Events

wafTotal, wafBlocked, wafChallenged, wafManagedChallenge

## Adaptive Time Resolution

- ≤1 hour: 1-minute
- ≤6 hours: 5-minute
- ≤24 hours: 15-minute
- ≤3 days: 1-hour
- >3 days: 6-hour

## Cloudflare API Token

Requires an API Token with:
- Account > Account Analytics > Read
- Zone > Analytics > Read

## License

MIT
