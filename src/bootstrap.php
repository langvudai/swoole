<?php

require_once __DIR__.'/../vendor/autoload.php';

// load .env
(function ($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null) {
    $dotenv = Dotenv\Dotenv::createImmutable($paths, $names, $shortCircuit, $fileEncoding);
    $dotenv->load();

    if (dotenv('APP_DEBUG')) {
        ini_set("display_errors",1);
        error_reporting(E_ALL);
    }

    return $dotenv;
})(dirname(__DIR__));

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(dotenv('TIMEZONE', 'Asia/Ho_Chi_Minh'));
}

$console = new SwooleBase\Foundation\Console(dirname(__DIR__), pathinfo(__DIR__, PATHINFO_FILENAME));
$console->classAlias([
    'DI' => SwooleBase\Foundation\DI::class,
]);

/*
|--------------------------------------------------------------------------
| Config connect to database
|--------------------------------------------------------------------------
*/
(function(string $driver, array $config) {
    $connection_name = $config['connection_name'] ?? $driver;
    Src\Database\PDO::config($connection_name, match ($driver) {
        Src\Database\Postgres\Driver::POSTGRESQL => Src\Database\Postgres\Driver::class,
        default => ''
    }, $config);
})(Src\Database\PDO::POSTGRESQL, [
    'HOST' => dotenv('DB_HOST', '127.0.0.1'),
    'PORT' => dotenv('DB_PORT', 5432),
    'DATABASE' => dotenv('DB_DATABASE', 'postgres'),
    'USERNAME' => dotenv('DB_USERNAME', 'postgres'),
    'PASSWORD' => dotenv('DB_PASSWORD', 'postgres'),
    'SCHEMA' => dotenv('DB_SCHEMA', 'public'),
    'TIMEZONE' => dotenv('TIMEZONE', 'Asia/Ho_Chi_Minh'),
]);

/*
|--------------------------------------------------------------------------
| directory container view
|--------------------------------------------------------------------------
*/
$console->accessible([SwooleBase\Foundation\Http\LoadHTML::class, '$base_path', 'resource/views']);

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| This could be a global middleware software running after each request
| and before routing dispatched to the request URI.
|
*/
$console->middlewares(Src\Middleware\LoadServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register alias route middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/
SwooleBase\Foundation\MiddlewareHandler::registerAlias([
    'auth' => \Src\Middleware\AuthenticateRoute::class,
    'cors' => \Src\Middleware\Cors::class,
]);

/*
|--------------------------------------------------------------------------
| Load Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/
$console->loadRoute(\Src\Api\Api::class);
$console->loadRoute(\Src\Web\Router::class);
/*
 * When using AJAX techniques in web applications, the OPTIONS method is primarily used to check permissions
 * and support CORS (Cross-Origin Resource Sharing) requests.
 */
$console->ajaxHandle(function(SwooleBase\Foundation\Interfaces\ResponseInterface $response) {
    $response->setHeader('Access-Control-Allow-Methods','*');
    $response->setHeader('Access-Control-Allow-Headers', '*');
    $response->setHeader('Access-Control-Allow-Origin','*');
});

return $console;
