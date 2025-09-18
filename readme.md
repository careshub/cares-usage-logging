# CARES Usage Logging

This plugin simply logs page load metrics to a shared CSV or ElasticSearch instance to help find pages that are inefficient. When writing to a CSV, it's intended for short-term use, because the file size could become quite large. 

To log data, I recommend adding the example CSV (`cares-shared-log.csv` in this directory) to the web server's httpd logs directory.

In your site's wp-config.php file, define the path to the CSV like (change the path for your server's architecture):

```
// CARES shared usage logging
if ( ! defined( 'CARES_USAGE_LOG' ) ) {
	define( 'CARES_USAGE_LOG', '/var/log/httpd/cares_shared_log.csv' );
}
```

If you don't want to track anonymous users (say you're mostly worried about some tools that logged-in users use), add this to your wp-config.php file:
```
// CARES shared usage logging - ignore anonymous visitors.
if ( ! defined( 'CARES_USAGE_LOG_IGNORE_ANON' ) ) {
	define( 'CARES_USAGE_LOG_IGNORE_ANON', true );
}
```

## Danger!!

The plugin doesn't attempt to rotate the CSV log file, so it's important to check back on it daily while actively profiling. The file could become unbelievably huge if you have a high-traffic site and ignore it.