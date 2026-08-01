<?php
/**
 * Copy this file to config/google-reviews.php on the server and fill in the
 * real values. Do not commit config/google-reviews.php.
 *
 * Two ways to reach the reviews:
 *
 *  1. Places API — needs only places_api_key + place_id, but Google caps the
 *     response at 5 reviews and offers no ordering control.
 *  2. Business Profile — needs the OAuth trio (client_id, client_secret,
 *     refresh_token) plus account_id and location_id. No review ceiling.
 *
 * The endpoint uses Business Profile when refresh_token, account_id and
 * location_id are all set; otherwise it falls back to the Places API.
 */
return [
    'places_api_key' => 'GOOGLE_PLACES_API_KEY',
    'place_id' => 'GOOGLE_PLACE_ID',
    // Only needed when the API key is locked to an HTTP referrer.
    'referrer' => 'https://example.com/',
    'client_id' => 'GOOGLE_OAUTH_CLIENT_ID',
    'client_secret' => 'GOOGLE_OAUTH_CLIENT_SECRET',
    'refresh_token' => 'GOOGLE_OAUTH_REFRESH_TOKEN',
    'account_id' => 'GOOGLE_BUSINESS_PROFILE_ACCOUNT_ID',
    'location_id' => 'GOOGLE_BUSINESS_PROFILE_LOCATION_ID',
    'cache_ttl' => 21600,
    'max_reviews' => 5,
];
