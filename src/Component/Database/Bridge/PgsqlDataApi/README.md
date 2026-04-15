# WpPack Aurora PostgreSQL Data API Database Driver

Aurora PostgreSQL Data API driver for the WpPack Database component. Uses RDS Data API (HTTP) instead of native PostgreSQL connections.

## Installation

```bash
composer require wppack/pgsql-data-api-database
```

## DSN Format

```
pgsql+dataapi://cluster-arn/dbname?secret_arn=arn:aws:secretsmanager:...&region=us-east-1
```
