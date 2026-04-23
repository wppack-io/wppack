# wppack/monitoring

Infrastructure monitoring abstraction for WordPress. Provides a provider-agnostic metric collection and caching layer with pluggable bridges.

## Installation

```bash
composer require wppack/monitoring
```

## Requirements

- PHP 8.2+

## Architecture

```
MonitoringProviderInterface    ← Discovers providers (auto-discovery, wp_options)
    ↓
MonitoringRegistry             ← Collects all providers
    ↓
MonitoringCollector            ← Queries bridges, caches results
    ↓
MetricProviderInterface        ← Bridge contract (query metrics)
    ├── CloudWatchMetricProvider (wppack/cloudwatch-monitoring)
    └── MockMetricProvider (built-in, local dev)
```

## Core Interfaces

### MetricProviderInterface

Bridge contract for querying metrics from external services (e.g., CloudWatch).

```php
interface MetricProviderInterface
{
    public function getName(): string;
    public function isAvailable(): bool;
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array;
}
```

### CollectableMetricProviderInterface

Extended bridge contract for providers that actively collect and store metrics (e.g., custom application metrics, APM). Unlike `MetricProviderInterface` which queries an external API on demand, collectable providers run periodic collection tasks.

```php
interface CollectableMetricProviderInterface extends MetricProviderInterface
{
    public function collect(MonitoringProvider $provider): void;
    public function getCollectInterval(): int;
}
```

### MonitoringProviderInterface

Discovers and provides monitoring provider configurations (auto-discovery, database storage).

```php
interface MonitoringProviderInterface
{
    /** @return list<MonitoringProvider> */
    public function getProviders(): array;
}
```

## Data Models

| Class | Description |
|-------|-------------|
| `MonitoringProvider` | Provider definition (id, label, bridge, settings, metrics) |
| `ProviderSettings` | Connection settings (region, credentials) |
| `MetricDefinition` | Metric specification (namespace, name, dimensions, period) |
| `MetricTimeRange` | Query time range (start, end, periodSeconds) |
| `MetricResult` | Query result (datapoints, error) |
| `MetricPoint` | Single data point (timestamp, value, stat) |

## Bridges

| Bridge | Package | Type | Description |
|--------|---------|------|-------------|
| CloudWatch | `wppack/cloudwatch-monitoring` | Query | AWS CloudWatch GetMetricData |
| Mock | Built-in | Query | Deterministic random data for local development |

## Further Reading

See [docs/components/monitoring/](../../../docs/components/monitoring/) for the full provider catalogue and integration guide.

## License

MIT
