# Cottage Passport Canada

## Overview
A Canadian cottage-themed stamp collection game. Users scan QR codes at various cottage locations and collect stamps in a digital passport booklet. Uses geolocation to verify the user is within 500m of a location.

## Project Architecture
- **Frontend**: Static HTML/CSS/JS (index.html, devtools.html, validation.html)
- **Backend API**: PHP (index.php) providing QR resolve, token register/validate, and admin generate endpoints
- **Craft CMS Module**: modules/cottagepassport/ (optional Craft CMS integration)
- **Router**: router.php - PHP built-in server router that serves static files and routes /api requests to index.php

## Key Files
- `index.html` - Main passport app UI
- `devtools.html` - Developer testing tools with geolocation simulation
- `app.js` - Core frontend JavaScript (IndexedDB, geolocation, stamp collection)
- `styles.css` - Passport-themed styling
- `valid-uuids.js` - Client-side UUID list
- `index.php` - Backend PHP API
- `router.php` - PHP built-in server router
- `config/valid-qr-uuids.php` - Server-side UUID allowlist

## Running
- PHP built-in server on port 5000: `php -S 0.0.0.0:5000 router.php`
- Static files served directly, /api routes to index.php

## Environment Variables (Development)
- `COTTAGE_AES_KEY` - AES-256 encryption key
- `COTTAGE_HMAC_SECRET` - HMAC signing secret
- `COTTAGE_VALIDATION_EMAIL` - Email for token validation
- `COTTAGE_ADMIN_KEY` - Admin endpoint auth key
- `COTTAGE_TOKEN_EXPIRY_DAYS` - Token lifetime (default: 7)
