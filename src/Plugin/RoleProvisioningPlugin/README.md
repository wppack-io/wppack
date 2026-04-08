# WpPack Role Provisioning Plugin

Rule-based role provisioning and blog membership management for WordPress.

## Features

- Assign roles to users based on configurable rules
- Support for user metadata conditions (SAML attributes, OAuth claims, etc.)
- Flexible condition engine with multiple operators
- Dynamic role templates from user metadata
- Multisite blog membership management
- SSO login sync (re-evaluate rules on every login)

## Installation

```bash
composer require wppack/role-provisioning-plugin
```

## Configuration

Settings are available in the WordPress admin under **Settings > Role Provisioning**.

### Rule Structure

Rules are evaluated top-down. The first matching rule is applied.

Each rule consists of:
- **Conditions** (AND): All conditions must match
- **Role**: WordPress role to assign (or `{{meta.<key>.<path>}}` template)
- **Blog IDs**: Target sites (null = all sites, array = specific sites)

### Condition Fields

| Field | Source | Example |
|-------|--------|---------|
| `user.email` | WP_User->user_email | `user@example.com` |
| `user.login` | WP_User->user_login | `john.doe` |
| `meta.<key>` | User meta value | `meta._wppack_sso_source` |
| `meta.<key>.<path>` | JSON dot-path in meta | `meta._wppack_saml_attributes.groups.0` |

### Operators

| Operator | Description |
|----------|-------------|
| `equals` | Exact match |
| `not_equals` | Not equal |
| `contains` | String contains / array includes |
| `starts_with` | Prefix match |
| `ends_with` | Suffix match |
| `matches` | Regex match |
| `exists` | Value exists (no value needed) |

### Hooks

| Timing | Hook | Purpose |
|--------|------|---------|
| New user | `user_register` | Initial role/blog assignment |
| SSO login (existing) | `SamlUserUpdatedEvent` / `OAuthUserUpdatedEvent` | Role sync on login |

## License

MIT
