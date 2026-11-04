<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Standalone localization contract check. Runs with PHP CLI without bootstrapping
 * the host application, so CI can catch untranslated UI before integration tests.
 */
$root = dirname(__DIR__);
$locales = [
    'en-GB' => require $root . '/Resources/lang/en-GB/general.php',
    'pt-BR' => require $root . '/Resources/lang/pt-BR/general.php',
];

function flattenTranslations(array $values, string $prefix = ''): array
{
    $flat = [];
    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $flat += flattenTranslations($value, $path);
            continue;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing or invalid translation: $path");
        }
        $flat[$path] = $value;
    }

    return $flat;
}

function placeholders(string $text): array
{
    preg_match_all('/:[a-z_][a-z0-9_]*/i', $text, $matches);
    $names = array_unique($matches[0]);
    sort($names);
    return $names;
}

$en = flattenTranslations($locales['en-GB']);
$pt = flattenTranslations($locales['pt-BR']);
$enKeys = array_keys($en);
$ptKeys = array_keys($pt);
sort($enKeys);
sort($ptKeys);

if ($enKeys !== $ptKeys) {
    throw new RuntimeException('Translation keys differ: ' . json_encode([
        'onlyEnglish' => array_values(array_diff($enKeys, $ptKeys)),
        'onlyPortuguese' => array_values(array_diff($ptKeys, $enKeys)),
    ], JSON_UNESCAPED_UNICODE));
}

foreach ($enKeys as $key) {
    if (placeholders($en[$key]) !== placeholders($pt[$key])) {
        throw new RuntimeException("Placeholder mismatch in $key");
    }
}
echo 'PASS: matching English/Portuguese keys and placeholders (' . count($enKeys) . " strings)\n";

// Verify every explicit translation key referenced by code has a definition.
// Any new locale is required to provide the same set.
$folders = [
    '/Domain', '/Http', '/Providers', '/Routes', '/Resources/views',
];
$references = [];
foreach ($folders as $folder) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . $folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            throw new RuntimeException("Cannot read {$file->getPathname()}");
        }

        preg_match_all(
            "/(?:trans|__)\(\s*['\"]field-studio::general\.([a-zA-Z0-9_.-]+)/",
            $source,
            $matches
        );

        foreach ($matches[1] as $key) {
            $references[$key] = true;
            if (!array_key_exists($key, $en)) {
                // Containers of translated arrays such as "entities" are allowed.
                $value = $locales['en-GB'];
                foreach (explode('.', $key) as $part) {
                    if (!is_array($value) || !array_key_exists($part, $value)) {
                        throw new RuntimeException("Missing translation $key in {$file->getPathname()}");
                    }
                    $value = $value[$part];
                }
            }
        }

        // User-visible strings must not be written in Portuguese in the source;
        // leave all human language in the locale catalog.
        if (preg_match('/[À-ÖØ-öø-ÿ]/u', $source)) {
            throw new RuntimeException("Non-ASCII text found outside locale catalogs: {$file->getPathname()}");
        }
    }
}
if (count($references) < 25) {
    throw new RuntimeException('Suspiciously few translation references found');
}
echo 'PASS: ' . count($references) . " source translation references resolve\n";
echo "PASS: no locale-specific text in PHP/Blade source\n";