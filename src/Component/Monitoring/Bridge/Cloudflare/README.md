# wppack/cloudflare-monitoring

Cloudflare Analytics bridge for the WpPack Monitoring component. Queries Cloudflare GraphQL Analytics API for CDN and WAF metrics.

## Installation

```bash
composer require wppack/cloudflare-monitoring
```

## Requirements

- PHP 8.2+
- `wppack/monitoring` ^1.0
- Cloudflare API Token with `Zone.Analytics` permission

## Metrics

### Zone Analytics (CDN)

| Metric | Description |
|--------|-------------|
| requests | Total HTTP requests |
| cachedRequests | Requests served from cache |
| bandwidth | Total bandwidth (bytes) |
| cachedBandwidth | Cached bandwidth (bytes) |
| threats | Threats blocked |
| pageViews | Page views |

### WAF

| Metric | Description |
|--------|-------------|
| wafBlocked | Requests blocked by WAF |

## API Token

Create a Cloudflare API Token with the following permission:

- **Zone** > **Analytics** > **Read**

## License

MIT
