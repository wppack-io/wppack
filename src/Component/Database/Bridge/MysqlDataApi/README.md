# WPPack Aurora MySQL Data API Database Driver

Aurora MySQL Data API driver for the WPPack Database component. Uses RDS Data API (HTTP) instead of native MySQL connections.

## Installation

```bash
composer require wppack/mysql-data-api-database
```

## DSN Format

```
mysql+dataapi://cluster-arn/dbname?secret_arn=arn:aws:secretsmanager:...&region=us-east-1
```
