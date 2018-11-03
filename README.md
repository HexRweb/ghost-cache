# ghost-cache

_this is a WIP script which depends on Ghost API 2.0 webhooks being fully implemented_

Ghost Cache is meant to be a simple repository of scripts / services which will handle refreshing caches when your Ghost site updates.

The purpose of Ghost Cache is to take your dynamic Ghost instance and put a fully static filesystem cache in front of it to limit requests to said Instance (and therefore improve performance). With upcoming WebHooks support as part of Ghost API 2.0, the goal is to create a minimal script which is called by the webhook in order to refresh the cache

The script is currently written in PHP because it's designed to execute on request rather than be a living service (like Node). This means memory is only required when the cache is being rebuilt.

There are future plans to better integrate with the Ghost API - hopefully to use the new Content API and only update the data that changed rather than rebuilding the entire cache which can be quite expensive. The current script uses the Sitemap, which has some weaknesses (the most notable being lastmodified for authors doesn't get updated when an author publishes a post), but will hopefully transform into one which uses API 2.0 when it's officially released.

This script is **not suitable for production** since there is are no security mechanisms in place to prevent unauthorized access.
