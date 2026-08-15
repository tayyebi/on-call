<?php
/** Shared semantic-HTML page shell. */
class OC_View
{
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function start(string $title, bool $withNav = true): void
    {
        echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            . "<link rel=\"stylesheet\" href=\"style.css\">"
            . "<title>on-call — " . self::e($title) . "</title></head>\n<body>\n";
        echo "<header><h1>on-call</h1></header>\n";
        if ($withNav) {
            self::nav();
        }
        echo "<main>\n";
    }

    public static function end(): void
    {
        echo "</main>\n<footer><p>on-call — remote device control</p></footer>\n</body>\n</html>\n";
    }

    private static function nav(): void
    {
        echo "<nav>\n<ul>\n"
            . "<li><a href=\"devices.php\">Devices</a></li>\n"
            . "<li><a href=\"on-board.php\">On-board device</a></li>\n"
            . "<li><a href=\"api.php\">Call log</a></li>\n"
            . "<li><a href=\"opendocs.json\">API docs</a></li>\n"
            . "<li><a href=\"logout.php\">Log out</a></li>\n"
            . "</ul>\n</nav>\n";
    }
}
