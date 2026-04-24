# Google Reviews Backend Setup

This site loads Google reviews through `api/google-reviews.php`. The browser never receives Google OAuth secrets.

## Required Google Values

Get these from a Google account that has Owner or Manager access to the business profile:

- `client_id`
- `client_secret`
- `refresh_token`
- `account_id`
- `location_id`

The OAuth consent must include this scope:

```text
https://www.googleapis.com/auth/business.manage
```

Google's review API endpoint used by the backend is:

```text
GET https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/reviews
```

## Server Config

Copy the example config:

```text
config/google-reviews.example.php
```

to:

```text
config/google-reviews.php
```

Then fill in the real credentials. `config/google-reviews.php` is ignored by Git and must not be committed.

You can also set these as server environment variables instead:

```text
GOOGLE_REVIEWS_CLIENT_ID
GOOGLE_REVIEWS_CLIENT_SECRET
GOOGLE_REVIEWS_REFRESH_TOKEN
GOOGLE_REVIEWS_ACCOUNT_ID
GOOGLE_REVIEWS_LOCATION_ID
GOOGLE_REVIEWS_CACHE_TTL
GOOGLE_REVIEWS_MAX_REVIEWS
```

## Cache

The endpoint caches Google responses in:

```text
data/google-reviews-cache.json
```

Default cache time is 6 hours. If Google is temporarily unavailable, the endpoint serves the stale cache instead of breaking the page.

## Frontend Behavior

`js/main.js` requests:

```text
api/google-reviews.php?limit=8
```

If the API is configured and working, the homepage review cards are replaced with live Google Business Profile reviews. If not, the existing static fallback reviews remain visible.
