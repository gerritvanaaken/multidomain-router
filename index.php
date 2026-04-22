<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Language;
use Kirby\Http\Response;

/**
 * Domain configuration for multi-domain installation
 * Reads configuration from Kirby config first, falls back to Site Settings (Panel)
 */
function getMultiDomains(): array
{
    // Method 1 (Preferred): Check Kirby config file
    $configSites = option('praegnanz.multidomain-router.sites', null);
    
    if ($configSites !== null && is_array($configSites) && !empty($configSites)) {
        // Process config file domains
        return array_map(function($site) {
            return [
                'folder' => $site['folder'] ?? '',
                'domain' => $site['domain'] ?? '',
                'error' => isset($site['error']) ? site()->find($site['error']) : null
            ];
        }, $configSites);
    }
    
    // Method 2 (Fallback): Read from Site Settings (Panel)
    $sites = site()->multidomains()->toStructure();
    
    if ($sites && $sites->isNotEmpty()) {
        // Convert Structure to array format
        return $sites->toArray(function($site) {
            return [
                'folder' => $site->folder()->value(),
                'domain' => $site->domain()->value(),
                'error' => $site->error()->toPage()
            ];
        });
    }
    
    return [];
}

/**
 * Gets the folder name from the domain configuration based on the current host
 * 
 * @param array $sites Array with domain configurations
 * @param string $host The host name
 * @return string|null The folder name or null if no match
 */
function getRequestFolder($sites, $host) {
    // Normalize host from request (strip optional port)
    $requestHost = strtolower(trim($host));
    if (strpos($requestHost, ':') !== false) {
        $requestHost = explode(':', $requestHost)[0];
    }

    foreach($sites as $site) {
        if (empty($site['domain']) === true) {
            continue;
        }

        $configuredHost = parse_url($site['domain'], PHP_URL_HOST);
        if ($configuredHost === null || $configuredHost === false) {
            // Fallback for malformed configuration without scheme
            $configuredHost = preg_replace('#^https?://#i', '', $site['domain']);
            $configuredHost = explode('/', $configuredHost)[0];
        }
        $configuredHost = strtolower(trim((string)$configuredHost));

        if ($configuredHost !== '' && $configuredHost === $requestHost) {
            return $site['folder'];
        }
    }
    return null;
}

/**
 * Returns all non-default language URL prefixes (without leading/trailing slash).
 * Uses Kirby's language path, not the language code, so custom prefixes work too.
 */
function getMultidomainLanguagePrefixes(): array
{
    $kirby = kirby();
    if (!$kirby->multilang()) {
        return [];
    }

    $prefixes = [];
    foreach ($kirby->languages() as $lang) {
        if ($lang->isDefault()) {
            continue;
        }

        $path = trim($lang->path(), '/');
        if ($path !== '') {
            $prefixes[$path] = $lang;
        }
    }

    // Longest prefixes first to support multi-segment prefixes
    // like "lang/en" before "lang".
    uksort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

    return $prefixes;
}

/**
 * Returns an optional regex group matching non-default language prefixes followed by "/".
 * Example result: "(?:(en|lang/en)/)?" or "" if there are no prefixes.
 */
function getMultidomainLangPattern(): string
{
    $prefixes = array_keys(getMultidomainLanguagePrefixes());
    if (empty($prefixes)) {
        return '';
    }

    $escaped = array_map(fn ($prefix) => preg_quote($prefix, '#'), $prefixes);
    return '(?:(' . implode('|', $escaped) . ')/)?';
}

/**
 * Finds a matching non-default language prefix at the beginning of a path.
 * Returns array with [Language|null, matchedPrefix].
 */
function matchMultidomainLanguagePrefix(string $path): array
{
    $trimmed = trim($path, '/');
    if ($trimmed === '') {
        return [null, ''];
    }

    foreach (getMultidomainLanguagePrefixes() as $prefix => $language) {
        if ($trimmed === $prefix || str_starts_with($trimmed, $prefix . '/')) {
            return [$language, $prefix];
        }
    }

    return [null, ''];
}

/**
 * Detects a language prefix at the beginning of a path and strips it.
 * Returns detected language code, language prefix for outgoing URLs and the stripped path.
 */
function resolveMultidomainLanguageFromPath(string $path): array
{
    $kirby = kirby();
    $result = [
        'path' => $path,
        'languageCode' => null,
        'languagePrefix' => ''
    ];

    if (!$kirby->multilang()) {
        return $result;
    }

    $default = $kirby->defaultLanguage();
    $result['languageCode'] = $default?->code();

    [$language, $matchedPrefix] = matchMultidomainLanguagePrefix($path);
    if ($language instanceof Language) {
        $trimmed = trim($path, '/');
        if ($trimmed === $matchedPrefix) {
            $trimmed = '';
        } else {
            $trimmed = substr($trimmed, strlen($matchedPrefix) + 1);
        }

        $result['path'] = $trimmed;
        $result['languageCode'] = $language->code();
        $result['languagePrefix'] = '/' . $matchedPrefix;
    }

    return $result;
}

/**
 * Force Kirby's current language/translation context.
 * This is needed in custom routes to ensure templates and fields
 * are rendered in the expected language.
 */
function applyMultidomainLanguageContext(?string $languageCode): void
{
    $kirby = kirby();
    if ($kirby->multilang() !== true || empty($languageCode) === true) {
        return;
    }

    $kirby->setCurrentLanguage($languageCode);
    $kirby->setCurrentTranslation($languageCode);
}

/**
 * Replaces URLs in HTML output based on domain configurations.
 * Supports Kirby multi-language URLs: the language prefix ("/en/...") is preserved
 * while the internal folder name ("/hotel-nepomuk/") is stripped or swapped.
 * 
 * @param string $output The HTML output
 * @param string $currentFolder The current folder (e.g. 'hotel-nepomuk')
 * @param array $sites Array with domain configurations
 * @return string The modified HTML output
 */
function replaceMultidomainUrls(string $output, string $currentFolder, array $sites): string
{
    $langGroup = getMultidomainLangPattern();
    $hasLang = $langGroup !== '';

    foreach ($sites as $targetDomain) {
        $targetFolder = $targetDomain['folder'];
        if ($targetFolder === '') {
            continue;
        }

        // Check if it's the current folder or a different one
        $isCurrentFolder = ($targetFolder === $currentFolder);

        // Target URL base: If current folder -> relative paths, otherwise absolute URL
        $replacementBase = $isCurrentFolder ? '' : $targetDomain['domain'];

        // 1. Replace full URLs with optional language prefix
        //    e.g. https://example.com/[lang/]example-domain-one/page
        $pattern1 = '#(https?://[^/]+)/' . $langGroup . preg_quote($targetFolder, '#') . '/([^"\'\s]*)#i';
        $output = preg_replace_callback($pattern1, function ($m) use ($replacementBase, $hasLang) {
            if ($hasLang) {
                $lang = $m[2] ?? '';
                $rest = $m[3] ?? '';
            } else {
                $lang = '';
                $rest = $m[2] ?? '';
            }
            $langPart = $lang !== '' ? '/' . $lang : '';
            return $replacementBase . $langPart . '/' . $rest;
        }, $output);

        // 2. Replace absolute paths with optional language prefix
        //    e.g. /[lang/]example-domain-one/page
        //    Require a leading "/" so the slash is part of the match and gets
        //    replaced alongside the folder name (otherwise cross-domain rewrites
        //    would produce broken urls like "/https://...").
        //    Skip matches inside /media/pages/ asset paths.
        // Important: do not touch media file urls, e.g.
        // /media/pages/<folder>/... because Kirby stores generated thumbs there.
        // The previous negative lookbehind with "/media/pages/" was too strict and
        // still matched in some cases; "/media/pages" is the correct anchor.
        $pattern2 = '#(?<!/media/pages)/' . $langGroup . preg_quote($targetFolder, '#') . '/([^"\'\s]*)#i';
        $output = preg_replace_callback($pattern2, function ($m) use ($replacementBase, $hasLang) {
            if ($hasLang) {
                $lang = $m[1] ?? '';
                $rest = $m[2] ?? '';
            } else {
                $lang = '';
                $rest = $m[1] ?? '';
            }
            $langPart = $lang !== '' ? '/' . $lang : '';
            return $replacementBase . $langPart . '/' . $rest;
        }, $output);
    }

    return $output;
}

Kirby::plugin('gerritvanaaken/multidomain-router', [
    'blueprints' => [
        'multidomain-router' => [
            'label' => 'Multidomains',
            'icon' => 'globe',
            'columns' => [
                [
                    'width' => '2/3',
                    'sections' => [
                        'fields' => [
                            'type' => 'fields',
                            'fields' => [
                                'multidomains' => [
                                    'label' => 'Multidomain Configuration',
                                    'type' => 'structure',
                                    'fields' => [
                                        'domain' => [
                                            'label' => 'Domain',
                                            'type' => 'url',
                                            'required' => true,
                                            'help' => 'Full URL including protocol, no trailing slash (e.g. "https://www.example-domain-one.com")',
                                        ],
                                        'folder' => [
                                            'label' => 'Folder',
                                            'type' => 'text',
                                            'required' => true,
                                            'help' => 'Content folder name (e.g. "example-domain-one"), without any slashes. Doesn’t have to be the same as the domain name, but it’s recommended to keep it simple.',
                                        ],
                                        'error' => [
                                            'label' => 'Error Page',
                                            'type' => 'pages',
                                            'required' => false,
                                            'multiple' => false,
                                            'help' => 'individual error page slug name (e.g. "error"), leave empty to use the default Kirby 404 page',
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'width' => '1/3',
                    'sections' => [
                        'info' => [
                            'type' => 'info',
                            'headline' => 'Information',
                            'text' => 'This configuration controls the multi-domain routing. Each domain is assigned to a content folder.'
                        ]
                    ]
                ]
            ]
        ]
    ],
    'routes' => [
        [
            'pattern'  => '(:all)',
            'action'   => function ($path = null) {
                $kirby = kirby();
                $host  = $_SERVER['HTTP_HOST'] ?? '';
                $sites = getMultiDomains();
                $hostfolder = getRequestFolder($sites, $host);

                $path = $path ?? '';
                $languageContext = resolveMultidomainLanguageFromPath($path);
                $path = $languageContext['path'];
                $languageCode = $languageContext['languageCode'];
                $languagePrefix = $languageContext['languagePrefix'];

                // Apply language context as early as possible so all subsequent
                // lookups (site()->find(), field access, rendering) happen in the
                // expected translation.
                applyMultidomainLanguageContext($languageCode);

                $pathStart = explode('/', $path)[0];

                // Serve media/assets files directly to avoid any page resolution in
                // multilingual language routers.
                if (in_array($pathStart, ['media', 'assets'], true)) {
                    $publicFile = $kirby->root('index') . '/' . $path;
                    if (is_file($publicFile) === true) {
                        return Response::file($publicFile);
                    }

                    // Never treat missing media/assets files as pages.
                    // Return a clean 404 instead of routing into content rendering.
                    return new Response('', 'text/plain', 404);
                }

                // Case 1: Hard Redirect, when visible url path starts with a folder
                // from plugin configuration. Preserve the language prefix.
                foreach ($sites as $site) {
                    if ($pathStart === $site['folder']) {
                        // Strip trailing slash
                        if ($path !== '' && substr($path, -1) === '/') {
                            $path = substr($path, 0, -1);
                        }

                        // Remove the folder name from the path (only the first occurence)
                        $path = preg_replace('/' . preg_quote($site['folder'], '/') . '/', '', $path, 1);

                        header('Location: ' . $site['domain'] . $languagePrefix . $path);
                        exit;
                    }
                }

                // Case 2: Visible URL does not contain an internal folder name from
                // plugin configuration, so silently fetch the correct page internally
                // and render it, not changing the visible URL.
                foreach ($sites as $site) {
                    if ($hostfolder !== null && strpos($hostfolder, $site['folder']) !== false) {
                        // Defensive: if for some reason the path still contains the folder, strip it
                        if ($pathStart === $site['folder']) {
                            $path = preg_replace('/' . preg_quote($site['folder'], '/') . '/', '', $path, 1);
                        }

                        $path = trim($path, '/');
                        $lookupPath = $path === '' ? $site['folder'] : $site['folder'] . '/' . $path;

                        $page = site()->find($lookupPath);

                        if (!$page) {
                            // Show 404 page
                            header('Status: 404 Not Found');
                            if (!empty($site['error'])) {
                                $page = site()->find($site['folder'] . '/' . $site['error']->slug());
                            } else {
                                $page = site()->find($site['folder'] . '/error');
                            }
                        }

                        // Visit the page, passing the language so Kirby renders the
                        // correct translation and generates language-aware URLs.
                        if ($kirby->multilang() && $languageCode) {
                            $renderpage = site()->visit($page, $languageCode);
                        } else {
                            $renderpage = site()->visit($page);
                        }

                        // Output buffering to manipulate HTML content
                        ob_start();
                        echo $renderpage->render();
                        $output = ob_get_clean();
                        $output = replaceMultidomainUrls($output, $site['folder'], $sites);
                        return new Response($output, 'text/html');
                    }
                }

                // Default behavior for main domain
                if ($kirby->multilang() && $languageCode) {
                    return site()->visit($path ?: 'home', $languageCode);
                }
                return site()->visit($path ?: 'home');
            }
        ]
    ]
]);
