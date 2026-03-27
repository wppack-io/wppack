# WpPack SCIM Plugin

A WordPress plugin that provides SCIM 2.0 (RFC 7643/7644) provisioning endpoints. Enables automatic user and group provisioning from Identity Providers such as Azure AD, Okta, and OneLogin.

## Installation

```bash
composer require wppack/scim-plugin
```

## Configuration

Set the following environment variables:

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SCIM_BEARER_TOKEN` | Yes | — | Bearer token for SCIM API authentication |
| `SCIM_SERVICE_ACCOUNT_USER_ID` | No | `1` | WordPress user ID for the SCIM service account |
| `SCIM_AUTO_PROVISION` | No | `true` | Enable automatic user provisioning |
| `SCIM_DEFAULT_ROLE` | No | `subscriber` | Default role for provisioned users |
| `SCIM_ALLOW_GROUP_MANAGEMENT` | No | `true` | Allow SCIM to manage WordPress roles |
| `SCIM_ALLOW_USER_DELETION` | No | `false` | Allow permanent user deletion (false = deactivate only) |
| `SCIM_BLOG_ID` | No | — | Target blog ID for multisite |
| `SCIM_MAX_RESULTS` | No | `100` | Maximum results per list request |

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/scim/v2/ServiceProviderConfig` | Service provider configuration |
| GET | `/wp-json/scim/v2/Schemas` | Schema definitions |
| GET | `/wp-json/scim/v2/ResourceTypes` | Resource type definitions |
| GET/POST | `/wp-json/scim/v2/Users` | List/create users |
| GET/PUT/PATCH/DELETE | `/wp-json/scim/v2/Users/{id}` | User operations |
| GET/POST | `/wp-json/scim/v2/Groups` | List/create groups |
| GET/PUT/PATCH/DELETE | `/wp-json/scim/v2/Groups/{id}` | Group operations |

## License

MIT
