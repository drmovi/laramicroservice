<?php

namespace Drmovi\MonorepoGenerator\Utils;

class FileUtil
{

    public static function removeDirectory($dir): void
    {
        if (!self::directoryExist($dir)) {
            return;
        }
        self::emptyDirectory($dir);
        rmdir($dir);
    }


    public static function copyDirectory(string $source, string $destination, array $replacements = []): void
    {
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sourceFile = $source . DIRECTORY_SEPARATOR . $file;
            $destinationFile = $destination . DIRECTORY_SEPARATOR . str_replace(array_keys($replacements), array_values($replacements), $file);
            if (is_dir($sourceFile)) {
                self::makeDirectory($destinationFile);
                self::copyDirectory($sourceFile, $destinationFile, $replacements);
            } else {
                self::copyFile($sourceFile, $destinationFile, $replacements);
            }
        }

    }

    public static function directoryExist(string $directory): bool
    {
        return is_dir($directory);
    }

    public static function copyFile(string $sourceFile, string $destinationFile, array $replacements): void
    {
        self::makeDirectory(dirname($destinationFile));
        $content = file_get_contents($sourceFile);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        file_put_contents($destinationFile, $content);
    }

    public static function makeFile(string $destinationFile, string $content): void
    {
        file_put_contents($destinationFile, $content);
    }

    public static function emptyDirectory(string $dir): void
    {

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? static::removeDirectory("$dir/$file") : self::removeFile("$dir/$file");
        }
    }

    public static function createSymLink(string $target, string $link): void
    {
        symlink($target, $link);
    }

    public static function removeFile(string $filePath): void
    {
        @unlink($filePath);
    }

    public static function makeDirectory(string $string): void
    {
        if (!is_dir($string)) {
            mkdir($string, 0777, true);
        }
    }
}
