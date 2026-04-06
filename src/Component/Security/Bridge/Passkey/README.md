# wppack/passkey-security

WebAuthn/Passkey authentication bridge for WpPack Security. Implements `AuthenticatorInterface` for passwordless login using FIDO2/WebAuthn passkeys.

## Installation

```bash
composer require wppack/passkey-security
```

## Requirements

- PHP 8.2+
- `wppack/security` ^1.0
- `web-auth/webauthn-lib` ^5.2

## Features

- Passkey registration and authentication via WebAuthn
- Multiple passkeys per user with device name management
- AAGUID-based device identification (iCloud Passkey, Google, YubiKey, etc.)
- Backup eligible (Synced) vs Device-bound classification
- Signature counter validation for clone detection
- Challenge management via WordPress Transient API

## License

MIT
