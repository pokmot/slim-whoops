<?php
namespace SlimWhoops;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

class Middleware {

    public $app;
    public $logger;

    public function __construct(\Slim\App $app, \Psr\Log\LoggerInterface $logger = null) {
        $this->app = $app;
        $this->logger = $logger;
    }

    public function __invoke($request, $response, $next) {
        $container = $this->app->getContainer();
        $settings  = $container['settings'];

        if (isset($settings['debug']) === true && $settings['debug'] === true) {
            // Enable PrettyPageHandler with editor options
            $prettyPageHandler = new PrettyPageHandler();

            if (empty($settings['whoops.editor']) === false) {
                $prettyPageHandler->setEditor($settings['whoops.editor']);
            }

            // Enable JsonResponseHandler when request is AJAX
            $jsonResponseHandler = new JsonResponseHandler();
            $jsonResponseHandler->onlyForAjaxRequests(true);

            // Add more information to the PrettyPageHandler
            $prettyPageHandler->addDataTable('Slim Application', [
                'Application Class' => get_class($this->app),
                'Script Name'       => $container['environment']->get('SCRIPT_NAME'),
                'Request URI'       => $container['environment']->get('PATH_INFO') ?: '<none>',
            ]);

            $prettyPageHandler->addDataTable('Slim Application (Request)', array(
                'Accept Charset'  => $container['request']->getHeader('ACCEPT_CHARSET') ?: '<none>',
                'Content Charset' => $container['request']->getContentCharset() ?: '<none>',
                'Path'            => $container['request']->getUri()->getPath(),
                'Query String'    => $container['request']->getUri()->getQuery() ?: '<none>',
                'HTTP Method'     => $container['request']->getMethod(),
                'Base URL'        => (string) $container['request']->getUri(),
                'Scheme'          => $container['request']->getUri()->getScheme(),
                'Port'            => $container['request']->getUri()->getPort(),
                'Host'            => $container['request']->getUri()->getHost(),
            ));

            // Set Whoops to default exception handler
            $whoops = new \Whoops\Run;
            $whoops->pushHandler($prettyPageHandler);
            $whoops->pushHandler($jsonResponseHandler);

            /*// Setup Monolog, for example:
            $logger = new \Monolog\Logger('Test');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler("c:/xampp/php/logs/php_error_log"));*/

            // Place our custom handler in front of the others, capturing exceptions
            // and logging them, then passing the exception on to the other handlers:
            if(!empty($logger = $this->logger)) {
                $whoops->pushHandler(function ($exception, $inspector, $run) use($logger) {
                    $logger->error($exception->getMessage());
                });
            }

            $whoops->register();

            $container['errorHandler'] = function($c) use ($whoops) {
            	return function($request, $response, $exception) use ($whoops) {
	            	$handler = \Whoops\Run::EXCEPTION_HANDLER;

				        ob_start();

				        $whoops->$handler($exception);

				        $content = ob_get_clean();
				        $code    = $exception instanceof HttpException ? $exception->getStatusCode() : 500;

				        return $response
				                ->withStatus($code)
				                ->withHeader('Content-type', 'text/html')
				                ->write($content);
	            };
	          };

            //
            $container['whoops'] = $whoops;
        }

        return $next($request, $response);
    }

}
