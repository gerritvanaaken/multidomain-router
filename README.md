# Multidomain Router

A Kirby plugin for routing and managing multiple domains within a single Kirby installation.

## How It Works

The plugin automatically handles routing for multiple domains and replaces URLs in the rendered HTML output:

1. **Routing**: Intercepts all requests and maps them to the correct content folder based on the domain
2. **Page Mapping**: URLs are mapped to the correct content folder (e.g. `/example-domain-one/`)
3. **URL Replacement**: All URLs are intelligently replaced in the rendered output:
   - **Internal links** (same folder): Converted to relative paths
   - **External links** (different folder): Receive the full absolute URL

## Installation (1/3)

### via composer

To install the Multidomain Router plugin via composer, run:

```bash
composer require praegnanz/kirby-multidomain-router
```

Make sure you are in the root directory of your Kirby project when running this command.

After installation, the plugin will be available at `site/plugins/multidomain-router/`. You can then continue with the configuration as described below.


### manually

Download and copy the plugin folder into `site/plugins/`.

## Domain Configuration (2/3)

There are two methods to configure your domains:

### Method 1: Config File (Recommended)

Add the domain configuration directly in your `site/config/config.php`:

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

### Method 2: Panel Configuration (Fallback)

If you prefer to configure domains through the Panel:

1. Add the Multidomain config to your `site/blueprints/site.yml`, using `extend`:

```yaml
title: Site

tabs:
  content:
    label: Seiten
    icon: page
    sections:
      pages:
        type: pages
  
  multidomains:
    extends: multidomain-router
```

2. Refresh the Panel and configure your domains:
   - Open the Panel and go to **Site Settings**
   - Locate the **"Multidomain"** section
   - Click **"+ Add"** to configure a new site
   - Enter the **folder name** (e.g. `example-domain-one`)
   - Enter the **domain** (e.g. `https://example-domain-one.com`)
   - Optionally select an **error page**
   - Don't forget to save! ✓

**Note:** If domains are configured in the config file (Method 1), the Panel configuration will be ignored.

## Folder creation (3)

To create the required content folders for each domain, follow these steps:

1. **Navigate to your site's `content` directory** (usually `site/content` in your Kirby installation).

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



## Examples

### Domain: https://hotel-kirby.de

- `/hotel-kirby/room` → `/room` (same folder = relative)
- `/restaurant-kirby/lunch` → `https://restaurant-kirby.de/lunch` (different folder = absolute)
- `https://hotel-kirby.de/hotel-kirby/room` → `/room` (same folder = relative)

### Domain: https://restaurant-kirby.de

- `/restaurant-kirby/lunch` → `/lunch` (same folder = relative)
- `/hotel-kirby/room` → `https://hotel-kirby.de/room` (different folder = absolute)

## License

MIT

## Author

Gerrit van Aaken (gerrit@praegnanz.de)

