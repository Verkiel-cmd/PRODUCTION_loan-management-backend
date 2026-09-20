<?php

/*
 * =============================================================================
 *  CORS CONFIGURATION
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  The React app runs on Vite's dev server (http://localhost:5173) while the
 *  Laravel API runs separately (http://localhost:8000). Those are two DIFFERENT
 *  origins to the browser, so every cross-origin /api/* request needs a CORS
 *  preflight/allowlist to pass. Because auth is COOKIE-BASED (see AuthController
 *  docblock), `supports_credentials` MUST be true or the browser silently drops
 *  the session cookie and the user can never log in.
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - Loaded automatically by Laravel (HandleCors global middleware reads
 *    `config/cors.php`). No bootstrap/app.php change is required.
 *  - The `paths` here cover every request the frontend makes: all /api/*
 *    (login, register, user, loans, ...).
 *
 *  WHERE IT IS CALLED
 *  ------------------
 *  - Every axios request from `src/api/axios.js` (baseURL http://localhost:8000
 *    with `withCredentials: true`). The browser enforces these rules before the
 *    response is ever seen by React.
 *  - If you deploy, replace the hard-coded dev origin below with your real
 *    frontend domain.
 *
 *  PATTERN
 *  -------
 *  Standard Laravel Lumen/Fortify-style cors.php:
 *    - allowed_methods/headers => '*' (we don't restrict verbs/headers here).
 *    - allowed_origins          => explicit dev origin (single-page app).
 *    - supports_credentials     => true (required for session cookies over CORS).
 * =============================================================================
 */

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173', 'https://loan-track-it.netlify.app'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];