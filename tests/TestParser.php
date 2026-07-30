<?php

declare(strict_types=1);

class TestParser
{
    public const FILEPATH = __DIR__.'/webhook.http';

    private static ?array $cache = null;

    public static function getRequests(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (!is_file(self::FILEPATH)) {
            throw new RuntimeException('HTTP file not found: '.self::FILEPATH);
        }

        $content = file_get_contents(self::FILEPATH);

        // Remplacement des variables (@baseUrl, ...)
        preg_match_all('/^@(\w+)\s*=\s*(.+)$/m', $content, $vars);

        foreach ($vars[1] as $i => $name) {
            $content = str_replace(
                '{{'.$name.'}}',
                trim($vars[2][$i]),
                $content
            );
        }

        // Supprime les lignes @xxx
        $content = preg_replace('/^@.+$/m', '', $content);

        // Découpe les scénarios
        $blocks = preg_split('/^\s*###\s*/m', trim($content));

        $requests = [];

        foreach ($blocks as $block) {

            $block = trim($block);

            if ($block === '') {
                continue;
            }

            $lines = preg_split('/\R/', $block);

            // Nom du scénario
            $title = trim(array_shift($lines));

            // Ligne HTTP
            if (!preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(.+)$/', array_shift($lines), $m)) {
                continue;
            }

            $method = $m[1];
            $url = trim($m[2]);

            // Headers
            $headers = [];

            while (!empty($lines) && trim($lines[0]) !== '') {
                $headers[] = trim(array_shift($lines));
            }

            // Ignore la ligne vide entre headers et body
            while (!empty($lines) && trim($lines[0]) === '') {
                array_shift($lines);
            }

            $requests[$title] = [
                'title'   => $title,
                'method'  => $method,
                'url'     => $url,
                'headers' => $headers,
                'body'    => implode("\n", $lines),
            ];
        }

        return self::$cache = $requests;
    }

    public static function getRequest(string $name): array
    {
        $requests = self::getRequests();

        if (!isset($requests[$name])) {
            throw new RuntimeException("Unknown scenario '{$name}'");
        }

        return $requests[$name];
    }

    public static function getScenario(string $name): array
    {
        return json_decode(
            self::getRequest($name)['body'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public static function getScenarioNames(): array
    {
        return array_keys(self::getRequests());
    }
}
