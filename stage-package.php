<?php
declare(strict_types=1);

[, $sourceRoot, $payload, $fallbackManifest] = $argv;
// Source repositories can ship this map next to their code. Signed release
// manifests (meta.json) instead describe the contents of the release ZIP.
$sourceManifest = "$sourceRoot/mybb-files.json";
$manifestPath = is_file($sourceManifest) ? $sourceManifest : $fallbackManifest;
if (is_link($manifestPath) || !is_file($manifestPath)) {
    throw new RuntimeException("Missing or unsafe file manifest: $manifestPath");
}
$manifest = json_decode(file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
$files = $manifest['files'] ?? null;
if (!is_array($files) || !array_is_list($files) || $files === []) {
    throw new RuntimeException("Expected a nonempty files list in $manifestPath");
}

function validatedPath(string $root, mixed $relative): string
{
    if (!is_string($relative) || !preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#D', $relative)) {
        throw new RuntimeException('Invalid package path.');
    }
    foreach (explode('/', $relative) as $component) {
        $root .= "/$component";
        if ($component === '.' || $component === '..' || is_link($root)) {
            throw new RuntimeException("Unsafe package path: $relative");
        }
    }
    return $root;
}

foreach ($files as $file) {
    $source = validatedPath($sourceRoot, $file['source'] ?? null);
    $destination = validatedPath($payload, $file['destination'] ?? null);
    if (!is_file($source) || file_exists($destination)) {
        throw new RuntimeException("Missing source or duplicate destination: $source → $destination");
    }
    $directory = dirname($destination);
    if ((!is_dir($directory) && !mkdir($directory, 0755, true))
        || !copy($source, $destination)
        || !chmod($destination, ($file['executable'] ?? false) === true ? 0755 : 0644)) {
        throw new RuntimeException("Cannot stage $destination");
    }
}
