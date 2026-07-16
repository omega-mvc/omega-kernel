<?php

declare(strict_types=1);

namespace Omega\Container;

use Exception;

use function array_diff;
use function array_key_exists;
use function array_merge;
use function array_reduce;
use function copy;
use function file_exists;
use function is_dir;
use function mkdir;
use function Omega\Application\slash;
use function pathinfo;
use function scandir;

use const PATHINFO_DIRNAME;

trait AppServiceProviderTrait
{
    /** @var array<string, array<string, string>> Shared modules available for import from vendor packages */
    protected static array $modules = [];

    /**
     * Import a specific file into the application.
     *
     * @param string $from      Source file path
     * @param string $to        Destination file path
     * @param bool   $overwrite Whether to overwrite the destination if it exists
     * @return bool Returns true if the file was successfully imported
     * @throws Exception If the destination file exists and overwriting is not allowed
     */
    public static function importFile(string $from, string $to, bool $overwrite = false): bool
    {
        if (!file_exists($to)) {
            return self::fileWrite($from, $to);
        }

        if (!$overwrite) {
            throw new Exception('You do not have permission to overwrite the destination file.');
        }

        return self::fileWrite($from, $to);
    }

    /**
     * Write a file to the destination path.
     *
     * Ensures that the destination directory exists before copying the file.
     * If the directory does not exist, it will be created recursively.
     *
     * @param string $from Source file path
     * @param string $to   Destination file path
     * @return bool True on success, false on failure
     */
    private static function fileWrite(string $from, string $to): bool
    {
        $path = pathinfo($to, PATHINFO_DIRNAME);

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        return copy($from, $to);
    }

    /**
     * Import a directory and its contents into the application.
     *
     * @param string $from      Source directory path
     * @param string $to        Destination directory path
     * @param bool   $overwrite Whether to overwrite existing files
     * @return bool Returns true if all files and directories were successfully imported
     * @throws Exception If the destination file exists and overwriting is not allowed
     */
    public static function importDir(string $from, string $to, bool $overwrite = false): bool
    {
        if (!is_dir($from)) {
            return false;
        }

        $dir = scandir($from);

        if ($dir === false) {
            return false;
        }

        if (!file_exists($to)) {
            mkdir($to, 0755, true);
        }

        $items = array_diff($dir, ['.', '..']);

        return array_reduce($items, function (bool $carry, string $file) use ($from, $to, $overwrite) {
            if (!$carry) {
                return false;
            }

            $src = slash(path: $from . '/' . $file);
            $dst = slash(path: $to . '/' . $file);

            return is_dir($src)
                ? static::importDir($src, $dst, $overwrite)
                : static::importFile($src, $dst, $overwrite);
        }, true);
    }

    /**
     * Register a package path to the module registry.
     *
     * @param array<string, string> $path Mapping of source to destination paths
     * @param string                $tag  Optional tag to group modules
     */
    public static function export(array $path, string $tag = ''): void
    {
        if (false === array_key_exists($tag, static::$modules)) {
            static::$modules[$tag] = [];
        }

        static::$modules[$tag] = array_merge(static::$modules[$tag], $path);
    }

    /**
     * Get all registered shared modules.
     *
     * @return array<string, array<string, string>> All modules grouped by tag
     */
    public static function getModules(): array
    {
        return static::$modules;
    }

    /**
     * Flush all registered shared modules.
     *
     * @return void
     */
    public static function flushModule(): void
    {
        static::$modules = [];
    }
}
