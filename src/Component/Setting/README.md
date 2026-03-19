# Setting Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=setting)](https://codecov.io/github/wppack-io/wppack)

**Package:** `wppack/setting`
**Namespace:** `WpPack\Component\Setting\`
**Layer:** Application

A component for working with the WordPress Settings API using modern PHP. Provides attribute-based settings page definition and Named Hook attributes.

## Installation

```bash
composer require wppack/setting
```

## Key Classes

| Class | Description |
|-------|-------------|
| `AsSettingsPage` | Class-level attribute for defining a settings page |
| `AbstractSettingsPage` | Base class for settings pages |
| `SettingsConfigurator` | Builder for defining sections and fields |
| `SectionDefinition` | Section definition |
| `FieldDefinition` | Field definition (value object) |
| `ValidationContext` | Notification for validation errors, warnings, and info |
| `SettingsRegistry` | Auto-registration registry for settings pages |

## Documentation

See [docs/components/setting/](../../../docs/components/setting/) for details.
