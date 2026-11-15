<?php

/**
 * Shows how much of the interface each language covers.
 *
 *   php tools/lang-check.php          one line per language
 *   php tools/lang-check.php fa       also lists the texts still shown in English
 *
 * A text counts when it is written as __('...') in app/, resources/views, config/ or routes/,
 * or is a 'label' / 'heading' in config/. Texts without a translation are shown in English.
 */
$root = dirname(__DIR__);
$keys = [];
foreach (['app', 'resources/views', 'config', 'routes'] as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $single);
        preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $code, $double);
        $found = array_merge(array_map(fn ($text) => str_replace(["\\'", '\\\\'], ["'", '\\'], $text), $single[1]), array_map(fn ($text) => str_replace('\\"', '"', $text), $double[1]));
        if ($dir === 'config') {
            preg_match_all("/'(?:label|heading)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $labels);
            $found = array_merge($found, array_map(fn ($text) => str_replace("\\'", "'", $text), $labels[1]));
        }
        foreach ($found as $text) {
            // app.nav.back and the like live in lang/<language>/app.php
            if ($text !== '' && ! preg_match('/^[a-z_]+(\.[a-z_0-9]+)+$/', $text)) {
                $keys[$text] = true;
            }
        }
    }
}
$keys = array_keys($keys);
sort($keys);

foreach (glob($root.'/lang/*.json') as $path) {
    $language = basename($path, '.json');
    $have = json_decode(file_get_contents($path), true) ?: [];
    $missing = array_values(array_filter($keys, fn ($key) => ! isset($have[$key])));
    printf("%s: %d of %d texts translated (%d%%)\n", $language, count($keys) - count($missing), count($keys), count($keys) ? round(100 * (count($keys) - count($missing)) / count($keys)) : 100);
    if (($argv[1] ?? null) === $language) {
        echo implode("\n", $missing), "\n";
    }
}
