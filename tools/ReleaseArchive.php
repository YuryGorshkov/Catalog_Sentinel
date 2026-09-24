<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class ReleaseArchive
{
    public const ROOT = 'gorshkov.catalogsentinel';
    private const DIRECTORIES = ['admin', 'install', 'lang', 'lib'];
    private const FILES = ['default_option.php', 'include.php', 'options.php', 'README.md', 'LICENSE'];
    private const FORBIDDEN = ['vendor', 'tests', 'tools', '.git', '.github', '.idea', '.vscode', 'PROJECT_STATUS.md'];

    /** @return array{path: string, sha256: string, files: int} */
    public function build(string $projectRoot, string $output): array
    {
        $version = $this->version($projectRoot);
        if (!str_contains(basename($output), $version)) {
            throw new RuntimeException('Archive name must contain module version ' . $version . '.');
        }
        $this->assertSource($projectRoot);
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create build directory.');
        }
        $zip = new ZipArchive();
        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create release archive.');
        }
        $count = 0;
        try {
            foreach (self::DIRECTORIES as $directoryName) {
                $count += $this->addDirectory($zip, $projectRoot . '/' . $directoryName, self::ROOT . '/' . $directoryName);
            }
            foreach (self::FILES as $file) {
                if (!$zip->addFile($projectRoot . '/' . $file, self::ROOT . '/' . $file)) {
                    throw new RuntimeException('Cannot add release file: ' . $file);
                }
                ++$count;
            }
        } finally {
            $zip->close();
        }

        return ['path' => realpath($output) ?: $output, 'sha256' => hash_file('sha256', $output), 'files' => $count];
    }

    /** @return array{version: string, files: int} */
    public function validate(string $archive): array
    {
        if (!is_file($archive)) {
            throw new RuntimeException('Release archive not found.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Cannot open release archive.');
        }
        $names = [];
        $versionContents = '';
        try {
            if ($zip->numFiles > 10_000) {
                throw new RuntimeException('Release archive contains too many entries.');
            }
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = $zip->getNameIndex($index);
                if ($name === false) {
                    throw new RuntimeException('Cannot read archive entry.');
                }
                $names[] = str_replace('\\', '/', $name);
            }
            $contents = $zip->getFromName(self::ROOT . '/install/version.php');
            $versionContents = is_string($contents) ? $contents : '';
        } finally {
            $zip->close();
        }
        foreach ($names as $name) {
            if (!str_starts_with($name, self::ROOT . '/')) {
                throw new RuntimeException('Archive contains an entry outside the expected root: ' . $name);
            }
            $parts = explode('/', $name);
            if (in_array('..', $parts, true)) {
                throw new RuntimeException('Archive path traversal is forbidden: ' . $name);
            }
            foreach (self::FORBIDDEN as $forbidden) {
                if (in_array($forbidden, $parts, true)) {
                    throw new RuntimeException('Forbidden release entry: ' . $name);
                }
            }
        }
        foreach (self::FILES as $required) {
            if (!in_array(self::ROOT . '/' . $required, $names, true)) {
                throw new RuntimeException('Required release entry is missing: ' . $required);
            }
        }
        foreach (['install/index.php', 'install/version.php', 'install/db/mysql/install.sql'] as $required) {
            if (!in_array(self::ROOT . '/' . $required, $names, true)) {
                throw new RuntimeException('Required runtime entry is missing: ' . $required);
            }
        }
        $temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gcs_validate_' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
            throw new RuntimeException('Cannot create validation directory.');
        }
        try {
            $extractor = new ZipArchive();
            if ($extractor->open($archive) !== true || !$extractor->extractTo($temporary)) {
                throw new RuntimeException('Cannot extract release archive.');
            }
            $extractor->close();
            $temporaryRoot = realpath($temporary);
            $root = realpath($temporary . DIRECTORY_SEPARATOR . self::ROOT);
            if ($temporaryRoot === false || $root === false || !str_starts_with($root, $temporaryRoot . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Extracted release root is invalid.');
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $entry) {
                if ($entry->isLink()) {
                    throw new RuntimeException('Extracted release contains a symlink.');
                }
            }
        } finally {
            $this->removeValidationDirectory($temporary);
        }

        $fileCount = count(array_filter($names, static fn (string $name): bool => !str_ends_with($name, '/')));

        return ['version' => $this->versionFromContents($versionContents), 'files' => $fileCount];
    }

    private function version(string $projectRoot): string
    {
        $contents = file_get_contents($projectRoot . '/install/version.php');
        if ($contents === false) {
            throw new RuntimeException('Invalid install/version.php.');
        }

        return $this->versionFromContents($contents);
    }

    private function versionFromContents(string $contents): string
    {
        if (preg_match("/'VERSION'\\s*=>\\s*'(\\d+\\.\\d+\\.\\d+)'/", $contents, $matches) !== 1) {
            throw new RuntimeException('Invalid install/version.php.');
        }

        return $matches[1];
    }

    private function removeValidationDirectory(string $directory): void
    {
        $resolved = realpath($directory);
        $temporaryRoot = realpath(sys_get_temp_dir());
        if (
            $resolved === false
            || $temporaryRoot === false
            || !str_starts_with($resolved, $temporaryRoot . DIRECTORY_SEPARATOR . 'gcs_validate_')
        ) {
            throw new RuntimeException('Refusing to remove an unexpected validation directory.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($resolved);
    }

    private function assertSource(string $projectRoot): void
    {
        foreach (array_merge(self::DIRECTORIES, self::FILES) as $required) {
            if (!file_exists($projectRoot . '/' . $required)) {
                throw new RuntimeException('Required source entry is missing: ' . $required);
            }
        }
    }

    private function addDirectory(ZipArchive $zip, string $source, string $destination): int
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        $count = 0;
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Symlinks are forbidden in releases: ' . $entry->getPathname());
            }
            $relative = substr($entry->getPathname(), strlen($source) + 1);
            $target = $destination . '/' . str_replace('\\', '/', $relative);
            if ($entry->isDir()) {
                $zip->addEmptyDir($target);
            } elseif ($entry->isFile()) {
                if (!$zip->addFile($entry->getPathname(), $target)) {
                    throw new RuntimeException('Cannot add file: ' . $entry->getPathname());
                }
                ++$count;
            }
        }

        return $count;
    }
}
