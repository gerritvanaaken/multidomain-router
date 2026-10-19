# Multidomain Router

A Kirby plugin for routing and managing multiple domains within a single Kirby installation.

## How It Works

The plugin automatically handles routing for multiple domains and replaces URLs in the rendered HTML output:

1. **Routing**: Intercepts all requests and maps them to the correct content folder based on the domain
2. **URL Replacement**: In the rendered output, all URLs are intelligently trimmed:
   - **links within current domain** (same folder): Converted to relative paths
   - **links to sibling domain** (different folder): Converted to the full absolute URLs

## Installation (1/4)

### via composer

To install the Multidomain Router plugin via composer, run:

```bash
composer require gerritvanaaken/multidomain-router
```

Make sure you are in the root directory of your Kirby project when running this command.

After installation, the plugin will be available at `site/plugins/multidomain-router/`. You can then continue with the configuration as described below.

### manually

Download and copy the full plugin folder into `site/plugins/`.

## Domain Configuration (2/4)

Add all the domains in your `site/config/config.php` like this:

```php
<?php

return [
    'praegnanz.multidomain-router.sites' => [
        [
            'domain' => 'https://example-domain-one.com',
            'folder' => 'example-domain-one',
            'error' => 'example-domain-one/error' //optional
        ],
        [
            'domain' => 'https://example-domain-two.com',
            'folder' => 'example-domain-two',
            'error' => 'example-domain-two/error' //optional
        ]
    ]
];
```

**Configuration Options:**

- `domain` (required): Full URL including protocol, no trailing slash
- `folder` (required): Content folder name, without any slashes
- `error` (optional): Path to custom error page (e.g. `'folder-name/error'`)

## Folder creation (3/4)

To create the required content folders for each domain, follow these steps:

1. **In the file system, navigate to your site's `content` directory** (usually `site/content` in your Kirby installation).

2. **Create a new folder for each domain.**
   - The folder name must exactly match the `folder` value you set in your multidomain configuration.
   - For example:
      ```
      site/content/example-domain-one/
      site/content/example-domain-two/
      ```

3. **Add content to each folder as usual.**
   - Each folder will represent the homepage and pages for that particular domain.
   - The typical structure inside a folder:
      ```
      site/content/example-domain-one/home.txt
      site/content/example-domain-one/1_rooms/rooms.txt
      site/content/example-domain-two/1_rooms/1_penthouse/room.txt
      ```
   - Add your regular Kirby pages (folders ending in `.txt`/subfolders) inside the domain folder as you would for a single-site setup.

**Tips:**

- If a domain has a custom error page, make sure to create the corresponding error page in the respective folder, e.g.:
   ```
   site/content/example-domain-one/error/default.txt
   site/content/example-domain-two/error/default.txt
   ```
- Folder names and structure should remain consistent with your configuration to ensure correct routing.

That's it! Each domain will serve content from its respective folder.

## Examples for URL replacements

### On https://hotel-kirby.de

- `/hotel-kirby/room` → `/room`
- `/restaurant-kirby/lunch` → `https://restaurant-kirby.de/lunch`
- `https://hotel-kirby.de/hotel-kirby/room` → `/room`

### On https://restaurant-kirby.de

- `/restaurant-kirby/lunch` → `/lunch`
- `/hotel-kirby/room` → `https://hotel-kirby.de/room`
- `https://restaurant-kirby.de/restaurant-kirby/lunch` → `/lunch`

## Kirby License activation (4/4)

**You will need to buy one Kirby license for each domain!** This plugin will take care of the license verification.

### License activation via the panel (recommended)

1. Log into panel under any of your domains
2. Activate the License (no different than you are used to)
3. Logout and login **under a different domain**
4. Repeat steps 1 and 2 until all domains have a proper license

### License activation via license file

If you prefer to manually download the license files from [Kirby License Hub](https://hub.getkirby.com):

1. Download the license file for each domain (`example1.com`, `example2.com` …).
2. Rename the file to `.example1folder.license`, `.example2folder.license` … (This is important and different from the instructions on the Hub). Use the exact folder name from the Multidomain configuration (see above!)
3. Place it in the `/site/config/` folder of your Kirby installation

## License

MIT

## Author

Gerrit van Aaken (gerrit@praegnanz.de)
