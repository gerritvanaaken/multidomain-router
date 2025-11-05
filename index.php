<?php

use Kirby\Cms\App as Kirby;
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
 * Replaces URLs in HTML output based on domain configurations
 * 
 * @param string $output The HTML output
 * @param string $currentFolder The current folder (e.g. 'hotel-nepomuk')
 * @param array $sites Array with domain configurations
 * @return string The modified HTML output
 */
function replaceMultidomainUrls(string $output, string $currentFolder, array $sites): string
{
    // Iterate through all domains and replace the URLs in the HTML output
    foreach ($sites as $targetDomain) {
        $targetFolder = $targetDomain['folder'];
        
        // Check if it's the current folder or a different one
        $isCurrentFolder = ($targetFolder === $currentFolder);
        
        // Target URL: If current folder -> relative paths, otherwise absolute URL
        $replacementUrl = $isCurrentFolder ? '' : $targetDomain['domain'];
        
        // 1. Replace full URLs with path_prefix
        // e.g. https://example.com/example-domain-one/page
        $pattern = '#(https?://[^/]+)/' . preg_quote($targetFolder, '#') . '/([^"\'\s]*)#i';
        $output = preg_replace(
            $pattern,
            $replacementUrl . '/$2',
            $output
        );
        
        // 2. Replace paths starting with /path_prefix
        // instead of using str_replace, use regex and replace only those strings that are not part of a full URL
        $output = preg_replace(
            '#' . preg_quote($targetFolder, '#') . '/([^"\'\s]*)#i',
            $replacementUrl . '/$1',
            $output
        );
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
            'pattern' => '(:all)',
            'action'  => function ($path = null) {
                $host = $_SERVER['HTTP_HOST'] ?? '';
                $sites = getMultiDomains();
                $pathStart = explode('/', $path)[0];
                
                // Case 1: Hard Redirect, when visible url path starts with a folder from plugin configuration
                foreach ($sites as $site) {
                    
                    if ($pathStart === $site['folder']) {
                        
                        // check if last character of $path is an '/' and remove it
                        if (substr($path, -1) === '/') {
                            $path = substr($path, 0, -1);
                        }

                        // remove the folder name from the path (only the first occurence)
                        $path = preg_replace('/' . preg_quote($site['folder'], '/') . '/', '', $path, 1);
                        
                        header('Location: ' . $site['domain'] . $path);
                        exit;
                    }
                }

                // Case 2: Visible URL does not contain an internal folder name from plugin configuration, so silently fetch the correct page internally and render it, not changing the visible URL

                foreach ($sites as $site) {
                    
                    if (strpos($host, $site['folder']) !== false) {

                        $path = $path ?: '';

                        if ($pathStart === $site['folder']) {
                            $path = preg_replace('/' . preg_quote($site['folder'], '/') . '/', '', $path, 1);
                        }

                        $page = site()->find($site['folder'] . '/' . $path);
                        
                        if (!$page) {
                            // Show 404 page
                            header('Status: 404 Not Found');
                            if (isset($site['error'])) {
                                $page = site()->find($site['folder'] . '/' . $site['error']->slug());
                            } else {
                                $page = site()->find($site['folder'] . '/error');
                            }
                        }
                        
                        // Visit page internally
                        $renderpage = site()->visit($page);
                        
                        // Output buffering to manipulate HTML content
                        ob_start();
                        echo $renderpage->render();
                        $output = ob_get_clean();
                        $output = replaceMultidomainUrls($output, $site['folder'], $sites);
                        
                        return new Response($output, 'text/html');
                    }
                }
                
                // Default behavior for main domain
                return site()->visit($path ?: 'home');
            }
        ]
    ]
]);

