# Multidomain Router

A Kirby plugin for routing and managing multiple domains within a single Kirby installation.

## How It Works

The plugin automatically handles routing for multiple domains and replaces URLs in the rendered HTML output:

1. **Routing**: Intercepts all requests and maps them to the correct content folder based on the domain
2. **Page Mapping**: URLs are mapped to the correct content folder (e.g. `/example-domain-one/`)
3. **URL Replacement**: All URLs are intelligently replaced in the rendered output:
   - **Internal links** (same folder): Converted to relative paths
   - **External links** (different folder): Receive the full absolute URL

## Installation

1. Copy the plugin folder to `site/plugins/multidomain-router/`
2. Configure your domains (see configuration methods below)
3. Create one top-level content folder for each desired domain

## Domain Configuration

There are two methods to configure your domains. **Method 1 (Config File)** is recommended and takes precedence over Method 2.

### Method 1: Config File (Recommended)

Add the domain configuration directly in your `site/config/config.php`:

```php
<?php

return [
    'praegnanz.multidomain-router.sites' => [
        [
            'domain' => 'https://example-domain-one.com', // with protocol and no trailing slash
            'folder' => 'example-domain-one', // no slashes
            'error' => 'example-domain-one/error' // optional: path to custom error page
        ],
        [
            'domain' => 'https://example-domain-two.com',
            'folder' => 'example-domain-two',
            'error' => 'example-domain-two/error'
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

## Examples

### On hotel-kirby.de

- `/hotel-kirby/room` → `/room` (same folder = relative)
- `/restaurant-kirby/lunch` → `https://restaurant-kirby.de/lunch` (different folder = absolute)
- `https://hotel-kirby.de/hotel-kirby/room` → `/room` (same folder = relative)

### On restaurant-kirby.de

- `/restaurant-kirby/lunch` → `/lunch` (same folder = relative)
- `/hotel-kirby/room` → `https://hotel-kirby.de/room` (different folder = absolute)

## Technical Details

The plugin registers a global route with the pattern `(:all)` that intercepts all requests. It uses:

- **Config File or Site Settings**: Multidomain configuration from `config.php` (preferred) or `site/content/site.txt` (fallback)
- **Configuration Precedence**: Config file takes priority over Panel settings
- **Global Routing**: Intercepts all HTTP requests before standard Kirby routing
- **Output Buffering** to capture the rendered HTML
- **Regex Pattern Matching** for URL replacement
- **String Replacement** for path replacement

### Data Storage

**Method 1 (Config File)**: Configuration is stored in `site/config/config.php`:

```php
'praegnanz.multidomain-router.sites' => [
    [
        'domain' => 'https://example-domain-one.com',
        'folder' => 'example-domain-one',
        'error' => 'example-domain-one/error'
    ]
]
```

**Method 2 (Panel)**: Configuration is stored as a Structure Field in `site.txt`:

```yaml
Multidomains:
- 
  domain: https://example-domain-one.com
  folder: example-domain-one
  error:
    - page://mgpdvfp7ddx9akic
- 
  domain: https://example-domain-two.com
  folder: example-domain-two
  error:
    - page://mgpdvfp7ddx9akic
```

## License

MIT

## Author

Gerrit van Aaken (gerrit@praegnanz.de)

