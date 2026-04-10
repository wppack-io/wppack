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

### Sync on Login Protection

When "Sync on Login" is enabled, the following safeguards apply:

- **Protected roles**: Users with protected roles (default: `administrator`) are never changed
- **Manual change detection**: When a role is set by provisioning, it's recorded in `_wppack_provisioned_role` meta. If an admin manually changes the role, the mismatch prevents re-evaluation on next login
- **First registration**: Only protected-role check applies; manual-change detection starts after the first provisioning

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
