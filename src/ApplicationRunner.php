<?php

declare(strict_types=1);

namespace App;

use Opis\Closure\SerializableClosure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Throwable;
use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\ErrorHandler\Renderer\JsonRenderer;
use Yiisoft\Factory\Definition\DefinitionInterface;
use Yiisoft\Factory\Definition\DynamicReference;
use Yiisoft\Http\Method;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Yii\Event\ListenerConfigurationChecker;
use Yiisoft\Yii\Web\Application;
use Yiisoft\Yii\Web\SapiEmitter;
use Yiisoft\Yii\Web\ServerRequestFactory;

use function dirname;
use function microtime;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ApplicationRunner
{
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
        $container = $this->wrapYiiConfigToSymfonyConfig($config->get('web'), $container);

        // Register error handler with real container-configured dependencies.
//        $this->registerErrorHandler($container->get(ErrorHandler::class), $errorHandler);

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

    private function wrapYiiConfigToSymfonyConfig(array $yiiDefinitions, ContainerInterface $yiiContainer)
    {
        $yiiDefinitions = [
            Stub::class => [
                'class' => Stub::class,
                '__construct()' => [
                    12345,
                    \Yiisoft\Factory\Definition\Reference::to(Stub2::class),
                    \Yiisoft\Factory\Definition\DynamicReference::to([
                        'class' => Stub2::class,
                        '__construct()' => [
                            12345,
                        ],
                    ]),
                    new \Yiisoft\Factory\Definition\CallableDefinition(fn() => 123),
                ],
            ],
            Stub1::class => function (Stub2 $stub2) {
                return new Stub1();
            },
            Stub2::class => [
                'class' => Stub2::class,
                '__construct()' => [
                    12345,
                ],
            ],
        ];
        $instanceof = [];
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setProxyInstantiator(new CallableInitiator(new RuntimeInstantiator()));
        $containerBuilder->addExpressionLanguageProvider(new CallableExpressionProvider());

        $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__));

//        $serviceConfigurator = new ServicesConfigurator($containerBuilder, $loader, $instanceof);
//        $serviceConfigurator
//            ->load('App\\', '../src/Installer*')
////            ->exclude('../src/{Dto,Auth,Blog,Exception,Factory,Formatter,User,Middleware,Provider}')
//            ->autoconfigure(true)
//            ->autowire(true);


//        $containerBuilder->set(Stub1::class, new Stub1());

//        $v = $containerBuilder->get(Stub1::class);
//        dd((class_implements($v)));

        $symfonyDefenitions = [];
        foreach ($yiiDefinitions as $class => $yiiDefinition) {
            $definition = $this->creatDefinition($yiiContainer, $class, $yiiDefinition);
            if ($definition instanceof Definition) {
                $containerBuilder->setDefinition($class, $definition);
            } else {
                $containerBuilder->set($class, $definition);
            }
            $symfonyDefenitions[$class] = $definition;
        }

//        var_dump($symfonyDefenitions);
        $containerBuilder->compile();
//        dd($containerBuilder->getDefinitions());
//        $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../config'));
//        $loader->load('services.php');
//        $s = $containerBuilder->get(RouteCollectionInterface::class);
        $s = $containerBuilder->get(Stub::class);
        dd($s);

        return $containerBuilder;
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

    private function creatDefinition(ContainerInterface $container, string $class, $yiiDefinition): Definition
    {
        $definition = new Definition($class);
        if (is_array($yiiDefinition)) {
            $arguments = $yiiDefinition['__construct()'] ?? [];
            $arguments = $this->processArguments($arguments, $container);
            $definition->setArguments($arguments);
        } else if (is_callable($yiiDefinition)) {
            $definition = new CallableDefinition();
            $definition->setLazy(true);
            $definition->setClosure($yiiDefinition);
        }
        $definition->setPublic(true);
//        $definition->setShared(true);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);
        return $definition;
    }

    private function processArguments(array $arguments)
    {
        $result = [];
        foreach ($arguments as $key => $argument) {
            $result[$key] = $this->processArgument($argument);
        }
        return $result;
    }

    private function processArgument(mixed $argument)
    {
        if ($argument instanceof \Closure) {
            $definition = new CallableDefinition();
            $definition->setLazy(true);
            $definition->setClosure($argument);
//            dd($argument);
            return $argument;
        }
        if ($argument instanceof \Yiisoft\Factory\Definition\Reference) {
            return new Reference($argument->getId());
        }

        if ($argument instanceof \Yiisoft\Factory\Definition\DynamicReference) {
            $ref = new \ReflectionObject($argument);
            $def = $ref->getProperty('definition');
            $def->setAccessible(true);
            /* @var DefinitionInterface $val */
            $val = $def->getValue($argument);
            return $this->processArgument($val);
        }

        if ($argument instanceof \Yiisoft\Factory\Definition\ArrayDefinition) {
//            dd($argument);
            return new Definition(
                $argument->getClass(),
                $this->processArguments($argument->getConstructorArguments())
            );
        }

        if ($argument instanceof \Yiisoft\Factory\Definition\CallableDefinition) {
            $ref = new \ReflectionObject($argument);
            $def = $ref->getProperty('method');
            $def->setAccessible(true);
            /* @var callable $val */
            $val = $def->getValue($argument);
            $definition = new Expression(sprintf(
                'closure("%s")',
                preg_quote(serialize(new SerializableClosure($val)), '"')
            ));
//            $definition->setClass('qq');
//            $definition->setClosure($this->processArgument($val));
            return $definition;
        }

        return $argument;
    }
}

class Stub
{
    private $value;
    private Stub2 $obj;
    private Stub2 $obj2;
    private int $int;

    public function __construct($value, Stub2 $obj, Stub2 $obj2, $int)
    {
//        dd(class_implements($int));
        $this->value = $value;
        $this->obj = $obj;
        $this->obj2 = $obj2;
        $this->int = $int;
    }
}

interface StubInterface
{
    public function doSmth();
}

class Stub1 implements StubInterface
{
    public function doSmth()
    {
        return 1234;
    }
}

class Stub2
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
