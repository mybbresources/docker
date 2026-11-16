<?php
declare(strict_types=1);

// Copy verified build inputs into the persistent document root before Apache starts.
[$script, $root] = $argv;
$payload = '/opt/mybb-defaults';
$state = 'inc/plugins/data/mybb-tooling/installed.json';
$ledgerPath = "$root/$state";

function checkedPath(string $root, string $relative): string
{
    foreach (explode('/', $relative) as $component) {
        $root .= "/$component";
        if (is_link($root)) {
            throw new RuntimeException("Bundled file path traverses a symlink: $relative");
        }
    }
    return $root;
}

checkedPath($root, $state);
$ledger = is_file($ledgerPath)
    ? json_decode(file_get_contents($ledgerPath), true, 64, JSON_THROW_ON_ERROR)
    : [];
if (!is_array($ledger)) {
    throw new RuntimeException('Invalid MyBB tooling ledger.');
}

$packages = [
    'mybb-tooling-core' => ['version' => '0.1.0', 'dependencies' => [], 'files' => []],
    'mybbresources-repository' => ['version' => '0.1.5', 'dependencies' => ['mybb-tooling-core' => '>=0.1.0 <1.0.0'], 'files' => []],
    'mybb-cli' => ['version' => '0.1.0', 'dependencies' => ['mybb-tooling-core' => '>=0.1.0 <1.0.0'], 'files' => []],
];

$copies = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($payload, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $source = $file->getPathname();
    $relative = substr($source, strlen($payload) + 1);
    $destination = checkedPath($root, $relative);
    $slug = match (true) {
        str_starts_with($relative, 'inc/plugins/mybb-tooling/'),
        $relative === 'inc/plugins/mybb_tooling_core.php' => 'mybb-tooling-core',
        str_contains($relative, 'mybbresources_repository.php') => 'mybbresources-repository',
        $relative === 'inc/plugins/data/mybb-tooling/bin/mybb' => 'mybb-cli',
        default => null,
    };

    if ($slug !== null && isset($ledger[$slug])) {
        // Preserve packages already managed by MyBB tooling.
        continue;
    }

    $digest = hash_file('sha256', $source);
    if (file_exists($destination)) {
        if (!is_file($destination) || ($slug !== null && hash_file('sha256', $destination) !== $digest)) {
            throw new RuntimeException("Conflicting bundled file: $relative");
        }
    } else {
        $copies[] = [$source, $destination, $file->getPerms() & 0777];
    }

    if ($slug !== null) {
        $role = match (true) {
            preg_match('#^inc/plugins/[^/]+\.php$#', $relative) === 1 => 'plugin',
            str_starts_with($relative, 'admin/') => 'admin',
            default => 'other',
        };
        $packages[$slug]['files'][$relative] = ['sha256' => $digest, 'role' => $role];
    }
}

foreach ($copies as [$source, $destination, $mode]) {
    $directory = dirname($destination);
    if ((!is_dir($directory) && !mkdir($directory, 0755, true))
        || !copy($source, $destination) || !chmod($destination, $mode)) {
        throw new RuntimeException("Cannot install bundled file: $destination");
    }
}

$newPackages = array_diff_key($packages, $ledger);
if ($newPackages !== []) {
    foreach ($newPackages as $slug => $package) {
        $ledger[$slug] = $package + ['active' => false, 'installed_at' => gmdate(DATE_ATOM), 'source' => 'docker-image'];
    }
    $temporary = tempnam(dirname($ledgerPath), '.installed-');
    if ($temporary === false) {
        throw new RuntimeException('Cannot create temporary tooling ledger.');
    }
    try {
        $json = json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($temporary, $json) !== strlen($json)
            || !chmod($temporary, 0640) || !rename($temporary, $ledgerPath)) {
            throw new RuntimeException('Cannot save the bundled tooling ledger.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

if (!chgrp($ledgerPath, 'www-data') || !chmod($ledgerPath, 0640)) {
    throw new RuntimeException('Cannot set the tooling ledger permissions.');
}
