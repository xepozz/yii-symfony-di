<?php

declare(strict_types=1);

namespace App;

use Closure;
use Opis\Closure\SerializableClosure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionObject;
use RuntimeException;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Throwable;
use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\Di\Contracts\ServiceProviderInterface;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\ErrorHandler\Renderer\JsonRenderer;
use Yiisoft\Factory\Definition\ArrayDefinition;
use Yiisoft\Factory\Definition\DefinitionInterface;
use Yiisoft\Factory\Definition\DynamicReference;
use Yiisoft\Http\Method;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\View\View;
use Yiisoft\Yii\Event\ListenerConfigurationChecker;
use Yiisoft\Yii\Web\Application;
use Yiisoft\Yii\Web\SapiEmitter;
use Yiisoft\Yii\Web\ServerRequestFactory;

use function dirname;
use function microtime;

final class ApplicationRunner
{
    public static $staticArguments = [];
    private static ?MyContainerBuilder $containerBuilder = null;
    private bool $debug = false;

    public function debug(bool $enable = true): void
    {
        $this->debug = $enable;
    }

    public function run(): void
    {
        $startTime = microtime(true);
        // Register temporary error handler to catch error while container is building.
        $tmpLogger = new Logger([new FileTarget(dirname(__DIR__) . '/runtime/logs/app.log')]);
        $errorHandler = new ErrorHandler($tmpLogger, new HtmlRenderer());
        $this->registerErrorHandler($errorHandler);


        [$config, $container] = $this->createYiiConfig();
        $wrapper = new SymfonyContainerWrapper($container);
        $container = $wrapper->wrap($config->get('web'), $config->get('providers'));

        // Register error handler with real container-configured dependencies.
//        $this->registerErrorHandler($container->get(ErrorHandler::class), $errorHandler);

//        $v = $container->get(View::class);
//        dd($v);

        $container = $container->get(ContainerInterface::class);
        //        var_dump($container);
//        exit();

        if ($this->debug) {
//            $container->get(ListenerConfigurationChecker::class)->check($config->get('events-web'));
        }

        $application = $container->get(Application::class);

        $request = $container->get(ServerRequestFactory::class)->createFromGlobals();
        $request = $request->withAttribute('applicationStartTime', $startTime);

        try {
            $application->start();
            $response = $application->handle($request);
            $this->emit($request, $response);
        } catch (Throwable $throwable) {
            $handler = $this->createThrowableHandler($throwable);
            $response = $container->get(ErrorCatcher::class)->process($request, $handler);
            $this->emit($request, $response);
        } finally {
            $application->afterEmit($response ?? null);
            $application->shutdown();
        }
    }

    private function createYiiConfig(): array
    {
        $config = new Config(
            dirname(__DIR__),
            '/config/packages',
        );

        $container = new Container(
            $config->get('web'),
            $config->get('providers'),
            [],
            null,
            $this->debug
        );

        return [$config, $container];
    }

    private function emit(RequestInterface $request, ResponseInterface $response): void
    {
        (new SapiEmitter())->emit($response, $request->getMethod() === Method::HEAD);
    }

    private function createThrowableHandler(Throwable $throwable): RequestHandlerInterface
    {
        return new class($throwable) implements RequestHandlerInterface {
            private Throwable $throwable;

            public function __construct(Throwable $throwable)
            {
                $this->throwable = $throwable;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->throwable;
            }
        };
    }

    private function registerErrorHandler(ErrorHandler $registered, ErrorHandler $unregistered = null): void
    {
        if ($unregistered !== null) {
            $unregistered->unregister();
        }

        if ($this->debug) {
            $registered->debug();
        }

        $registered->register();
    }

}
