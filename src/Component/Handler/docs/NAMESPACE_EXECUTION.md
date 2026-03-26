# Namespace Execution Model

## Overview

The Handler operates in a unique execution context: it runs **before** WordPress is loaded. This means WordPress functions, hooks, and globals are not available during request processing.

## Execution Timeline

```
1. web/index.php
   └─ require autoload.php
   └─ $result = Handler::run()
      ├─ Request::createFromGlobals()  ← No WordPress yet
      ├─ Environment::setup()          ← Lambda directory creation
      ├─ Processor chain               ← All processors run without WordPress
      │  ├─ SecurityProcessor
      │  ├─ MultisiteProcessor
      │  ├─ TrailingSlashProcessor
      │  ├─ DirectoryProcessor
      │  ├─ StaticFileProcessor        ← Static files served here (no WordPress)
      │  ├─ PhpFileProcessor
      │  └─ WordPressProcessor         ← Sets SCRIPT_FILENAME to WP index.php
      ├─ preparePhpEnvironment()       ← Sets $_SERVER variables
      ├─ Kernel::create($request)      ← Kernel instance created + Request stored (pre-WP)
      └─ return $filePath              ← File path returned to caller
   └─ require $result                  ← WordPress loads HERE (in global scope)
      └─ wp-settings.php
         ├─ plugins_loaded             ← Kernel::registerPlugin() called
         │                                (autoBoot hook registered on first addPlugin/addTheme)
         ├─ wp_magic_quotes()          ← Superglobals modified
         └─ init (priority 0)          ← Kernel::autoBoot() → boot()
            └─ Uses stored Request (not createFromGlobals)
```

## Key Constraints

### No WordPress Functions in Processors

Processors must not call WordPress functions (`add_action`, `get_option`, etc.). They operate on the raw `Request` object and filesystem only.

### MimeTypes Without WordPress

The `StaticFileProcessor` uses `WpPack\Component\Mime\MimeTypes` for MIME type detection. The `guessMimeType()` method works without WordPress via:
- `ExtensionMimeTypeGuesser` — Uses the `MimeTypeMap` constant (pure PHP)
- `FileinfoMimeTypeGuesser` — Uses PHP's `finfo` extension

### Request Object Lifecycle

The `Request` created by Handler is passed to `Kernel::create()` and reused during `Kernel::boot()`. This avoids creating a second `Request::createFromGlobals()` after `wp_magic_quotes()` has modified superglobals.
