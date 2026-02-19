# Cottage Passport Canada

## Overview
A Canadian cottage-themed stamp collection game. Users scan QR codes at various cottage locations and collect stamps in a digital passport booklet. Uses geolocation to verify the user is within 550m of a location.

## Project Architecture
- **Frontend**: Static HTML/CSS/JS (index.html, devtools.html, validation.html)
- **Backend API**: PHP (index.php) providing QR resolve, token register/validate, and admin generate endpoints
- **Craft CMS Module**: modules/cottagepassport/ (optional Craft CMS integration)
- **Router**: router.php - PHP built-in server router that serves static files and routes ?action= requests to index.php

## Key Files
- `index.html` - Main passport app UI (uses app.js)
- `devtools.html` - Developer testing tools with geolocation simulation (uses devtools.js, blocked in production)
- `app.js` - Production frontend JavaScript (IndexedDB, geolocation, stamp collection, no devtools code)
- `devtools.js` - Devtools-only JavaScript (geo simulation, location picker, testing features, blocked in production)
- `styles.css` - Passport-themed styling
- `index.php` - Backend PHP API
- `router.php` - PHP built-in server router with production security blocking
- `config/valid-qr-uuids.php` - Server-side UUID allowlist
- `config/locations.php` - Server-side location definitions

## Security
- `app.js` and `devtools.js` are fully separated; app.js contains zero devtools code
- `devtools.html`, `devtools.js`, and `/devtools` route are blocked (404) in production via router.php
- Production blocking uses `REPLIT_DEPLOYMENT` env var detection
- Encryption-dependent API endpoints (resolve, register, validate, generate) fail gracefully if env vars are missing
- Public endpoints (locations, collect) work without encryption keys

## Running
- PHP built-in server on port 5000: `php -S 0.0.0.0:5000 router.php`
- Static files served directly, ?action= routes to index.php

## Environment Variables (Shared)
- `COTTAGE_AES_KEY` - AES-256 encryption key
- `COTTAGE_HMAC_SECRET` - HMAC signing secret
- `COTTAGE_VALIDATION_EMAIL` - Email for token validation
- `COTTAGE_ADMIN_KEY` - Admin endpoint auth key
- `COTTAGE_TOKEN_EXPIRY_DAYS` - Token lifetime (default: 7)
