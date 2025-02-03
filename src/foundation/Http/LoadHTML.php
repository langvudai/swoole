<?php

namespace SwooleBase\Foundation\Http;

use FastRoute\RouteParser\Std;
use SwooleBase\Foundation\Interfaces\HasResponse;
use SwooleBase\Foundation\Interfaces\ResponseInterface;
use SwooleBase\Foundation\Exception;
use function SwooleBase\Foundation\console;

class LoadHTML implements HasResponse
{
    private $data;

    private $path;

    private static $base_path = 'resource/views';

    private $routes_registered;

    private $std;

    public function __construct(string $path, array $data = [])
    {
        $file = sprintf('%s/%s.php', console()->basePath(self::$base_path), preg_replace('/(\.php)$/i', '', $path));

        if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
            throw new Exception('No template found at path '.$file);
        }

        $this->path = $file;
        $this->routes_registered = console(RouteCollector::class, '$routes_registered');
        $this->std = new Std();

        if (!empty($data)) {
            $this->data = $data;
        }

    }

    public function respond(ResponseInterface $response)
    {
        $response->setContent($this->getContent());
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $keys = explode('.', $name);
        if (1 === count($keys)) {
            return $this->data[$name] ?? null;
        }

        $array = $this->data;

        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return null;
            }
        }

        return $array;
    }

    /**
     * @return string
     */
    private function getContent(): string
    {
        if (!$this->path) {
            return '';
        }

        ob_start();
        include $this->path;
        $content = ob_get_clean();

        if (!$content) {
            return '';
        }

        $content = preg_replace(array('/\\\\{/s', '/\\\\}/s'), array('&#123;', '&#125;'), $content);
        $content = preg_replace_callback('/\{\s*([^\{\}\r\n]+)\s*\}/is', function ($matches) {
            $replace = $this->renderContent($matches[1]);

            if ($replace) {
                if (!is_scalar($replace)) {
                    $json = json_encode($replace);

                    if (false !== $json) {
                        return (string)$json;
                    }
                } else {
                    return (string)$replace;
                }
            }

            return sprintf('&#123;%s&#125;', $matches[1]);
        }, $content);

        return preg_replace(['/&#123;/s', '/&#125;/s'], ['{', '}'], $content);
    }

    /**
     * @param string $match
     * @return mixed
     */
    private function renderContent(string $match): mixed
    {
        if (preg_match('/^\$([a-z][a-z0-9\.]*)$/', $match, $matches)) {
            return $this->{$matches[1]};
        }

        if (preg_match('/^url\(([^\(\)]+)\)$/i', $match, $matches)) {
            return $this->url($matches[1]);
        }

        if (preg_match('/^js:\$([a-z][a-z0-9\.]*)$/', $match, $matches)) {
            return $this->jsData($matches[1]);
        }

        return null;
    }

    /**
     * @param $argument
     * @return string|null
     */
    private function url($argument): ?string
    {
        $params = array_map('trim', explode(',', $argument));
        $as = array_shift($params);
        $search = array_search($as, $this->routes_registered);
        $analysis = $this->std->parse($search)[0] ?? [];

        if (!$analysis) {
            return null;
        }

        $analysis = array_map(fn($item) => is_array($item) && $params[0] ? array_shift($params) : $item, $analysis);

        if (count(array_filter($analysis, fn($item) => !is_string($item))) > 0) {
            return null;
        }

        return (string)preg_replace('/^([A-Z]+)/', dotenv('APP_URL'), implode($analysis));
    }

    private function jsData($argument)
    {
        $data = $this->{$argument};

        if (is_scalar($data)) {
            return is_numeric($data) ? $data : ('"'.addcslashes($data, '"').'"');
        }

        return json_encode($data);
    }
}
