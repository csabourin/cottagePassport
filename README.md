# Stamp Passport

Stamp Passport is a Craft CMS plugin for QR-based passport or challenge experiences with location-aware check-ins.

## Features

- Custom CP section to manage passport locations and content
- Frontend passport page with progress tracking
- QR code resolver and check-in endpoints
- Optional geofence validation for check-ins
- Per-site display text overrides with neutral built-in defaults
- Optional Freeform integration for reward or draw forms

## Requirements

- Craft CMS 4.x
- PHP 8.0+

## Installation

1. Open your Craft project in a terminal.
2. Require the plugin package:

```bash
composer require csabourin/craft-stamp-passport
```

3. Install the plugin in the Craft control panel, or run:

```bash
php craft plugin/install stamp-passport
```

## Configuration

1. Go to **Stamp Passport → Settings**.
2. Configure route prefix, geofence settings, reward thresholds, and optional assets.
3. Go to **Stamp Passport → Items** to create passport locations and per-site content.
4. Optionally customize per-site display text. Leaving fields blank uses neutral defaults.

## Frontend Route

The frontend route uses the configured route prefix (default: `passport`).

Example:

- `/passport`

## License

This project is licensed under the [MIT License](LICENSE).
