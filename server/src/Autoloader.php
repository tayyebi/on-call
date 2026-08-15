<?php
/**
 * Single-file-friendly autoloader.
 *
 * Maps class name "OC_Xyz" to src/Xyz.php. Deliberately flat (no namespaces,
 * no composer) so that build.php can concatenate every src/*.php into one
 * release artifact without import/namespace rewriting.
 */

class OC_Autoloader
{
    /** @var string[] class => absolute file path, filled in as files are discovered */
    private static array $map = [];

    public static function register(string $srcDir): void
    {
        foreach (glob(rtrim($srcDir, '/') . '/*.php') as $file) {
            $class = basename($file, '.php');
            if ($class === 'Autoloader') {
                continue;
            }
            self::$map['OC_' . $class] = $file;
        }

        spl_autoload_register(static function (string $class): void {
            if (isset(self::$map[$class])) {
                require_once self::$map[$class];
            }
        });
    }

    /** @return string[] absolute paths of every class file, in load order, for build.php */
    public static function classFiles(string $srcDir): array
    {
        $files = glob(rtrim($srcDir, '/') . '/*.php');
        sort($files);
        return array_values(array_filter($files, static fn ($f) => basename($f) !== 'Autoloader.php'));
    }
}
