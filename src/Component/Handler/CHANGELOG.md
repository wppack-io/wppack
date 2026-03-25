# CHANGELOG

## 1.0.0

- Migrated from `wpnx/handler` to `wppack/handler`
- Changed `run()` to `handle(Request): void` — Handler now manages the full lifecycle
- Replaced Symfony HttpFoundation/Mime with WpPack equivalents
- Added optional Kernel integration via `class_exists(Kernel::class)`
- Added `ExceptionInterface` marker interface
- Replaced `error_log()` with `Psr\Log\LoggerInterface`
- Renamed directories: `Processors/` → `Processor/`, `Exceptions/` → `Exception/`
- Requires PHP 8.2+
