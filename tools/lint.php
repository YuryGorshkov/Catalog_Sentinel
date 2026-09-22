<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['admin', 'install', 'lang', 'lib', 'tests', 'tools'];
$files = [];

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

foreach (['include.php', 'options.php', 'default_option.php', '.settings.php'] as $entrypoint) {
    $path = $root . DIRECTORY_SEPARATOR . $entrypoint;
    if (is_file($path)) {
        $files[] = $path;
    }
}

sort($files);
$failed = false;
foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed = true;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
    $output = [];
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, sprintf("Syntax OK (%d PHP files)%s", count($files), PHP_EOL));
