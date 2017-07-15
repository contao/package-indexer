# Packagist API

Our goal is to fetch package details from packagist and update
our Algolia search index. Ideally, both should be in sync, but that
is obviously only best-effort.

Approach:

1. run cron job every 5 minutes
2. fetch list of package names
3. fetch *Packagist Meta Information* about the package. As there is
   12 hours cache time on the data, we can only use statistical infos
   from there. Currently includes downloads and favers. Let the
   [Guzzle Cache Middleware] handle caching and automatic re-validation.
4. fetch Composer Data to read description, keywords and other meta data
   about the package. Let [Guzzle Cache Middleware] handle automatic
   re-validation based on *Etag* or *Modified-Since* headers.


 - Using both API end points will make sure we fetch the latest Composer
   data whenever there is an update.
 - Using cache information will make sure we don't DDoS the Packagist API.


## Useful endpoints

### Package names

> https://packagist.org/packages/list.json?type={type}

Lists package names by type. Has a public cache time of 5 minutes.


### Packagist Meta Info

> https://packagist.org/packages/{vendor/package}.json

List Packagist meta information. Includes *stars*, *downloads* etc.
Has a public cache time of 12 hours.


### Composer Data

> https://packagist.org/p/{vendor/package}.json

Returns array of composer.json by version. Has *Last-Modified*
and *Etag* cache headers for revalidation.


[Guzzle Cache Middleware]: https://github.com/Kevinrob/guzzle-cache-middleware
