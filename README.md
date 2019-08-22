# URL Checker

## Used for pre / post launch SEO checks of urls in bulk.

Returrns the status code, title, and redirect count.

To use, add the following to composer.json:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/thinktandem/url_checker"
        },
    ]
```

Then run:

```bash
composer require "thinktandem/url_checker:dev-master"
```

Install the module as any other drupal module, then go to:

```
/admin/config/services/url-checker
```

To either copy and paste your urls or use a text file of urls to check.