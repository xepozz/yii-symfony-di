<?php
declare(strict_types=1);

namespace App;

use Opis\Closure\SerializableClosure;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use RuntimeException;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Throwable;
use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\Di\Contracts\ServiceProviderInterface;
use Yiisoft\Factory\Definition\ArrayDefinition;
use Yiisoft\Factory\Definition\DefinitionInterface;
use Yiisoft\Factory\Definition\DynamicReference;

class SymfonyContainerWrapper
{
    public static $staticArguments = [];
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function wrap(array $yiiDefinitions, array $yiiProviders): ContainerInterface
    {
        $instanceof = [];
        $containerBuilder = new MyContainerBuilder($this->container);
//        $containerBuilder->merge();
        $containerBuilder->setProxyInstantiator(new CallableInitiator(new RuntimeInstantiator()));
        $containerBuilder->addExpressionLanguageProvider(new CallableExpressionProvider());
        $containerBuilder->addExpressionLanguageProvider(new ObjectExpressionProvider());

        $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__));

        $serviceConfigurator = new ServicesConfigurator($containerBuilder, $loader, $instanceof);
        $serviceConfigurator
            ->defaults()
            ->public(true)
            ->autowire(true)
            ->autoconfigure(true);


        $yiiDefinitions2 = [
            Stub::class => [
                'class' => Stub::class,
                '__construct()' => [
                    12345,
//                    \Yiisoft\Factory\Definition\Reference::to(Stub2::class),
                    new Stub2(5555555),
                    DynamicReference::to([
                        'class' => Stub2::class,
                        '__construct()' => [
                            12345,
                        ],
                    ]),
                    new \Yiisoft\Factory\Definition\CallableDefinition(fn() => 123),
                ],
            ],
            Stub1::class => new Stub1(),
            Stub2::class => [
                'class' => Stub2::class,
                '__construct()' => [
                    12345,
                ],
            ],
        ];
        $this->loadThirdPartyServices($serviceConfigurator);
        $this->ignoreNonServices($serviceConfigurator);


       $proxy = $this->wrapInternal($containerBuilder, $yiiDefinitions, $yiiProviders);

        $isDebug = false;
        $file = __DIR__ .'/../cache/container.php';
//        dd($file);
        $containerConfigCache = new ConfigCache($file, $isDebug);

        if (!$containerConfigCache->isFresh()) {
            $dumper = new PhpDumper($containerBuilder);
            $containerConfigCache->write(
                $dumper->dump(['class' => 'CachedContainer']),
                $containerBuilder->getResources()
            );
        }
        $proxy->injectServices();
        foreach (self::$staticArguments as $id => $service) {
            $containerBuilder->set($id, $service);
//            dd($argument,$class);
        }
        return $containerBuilder;
    }

    private function wrapInternal(ContainerBuilder $containerBuilder, array $yiiDefinitions, array $yiiProviders)
    {
//        $containerBuilder->set(Stub1::class, new Stub1());

//        $v = $containerBuilder->get(Stub1::class);
//        dd((class_implements($v)));

        foreach ($yiiDefinitions as $class => $yiiDefinition) {
            if ($class === 'Yiisoft\\Cache\\File\\FileCache') {
                $var = true;
            }
            if ($class === 'Yiisoft\\DataResponse\\DataResponseFactoryInterface') {
                $var = true;
            }
            if ($class === 'App\\Blog\\PostRepository') {
                $var = true;
            }
            if ($class === 'App\\Blog\\BlogService') {
                $var = true;
            }
            $definition = $this->creatDefinition($class, $yiiDefinition);
            if ($definition instanceof Definition) {
                $containerBuilder->setDefinition($class, $definition);
            } elseif ($definition instanceof Reference) {
                $containerBuilder->set($class, $definition);
            } else {
                $containerBuilder->setDefinition($class, $definition);
                $containerBuilder->set($class, $definition);
            }
        }
        $proxy = new ContainerConfigProxy($containerBuilder);
        foreach ($yiiProviders as $yiiProvider) {
            /* @var ServiceProviderInterface $provider */
            $provider = new $yiiProvider;
            $provider->register($proxy);
        }
//        var_dump($symfonyDefenitions);
        $containerBuilder->compile();
//        dd($containerBuilder->getDefinitions());
//        $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../config'));
//        $loader->load('services.php');
//        $s = $containerBuilder->get(RouteCollectionInterface::class);
//        $s = $containerBuilder->get(Stub1::class);
//        dd($s);

        return $proxy;
    }

    private function creatDefinition(string $class, $yiiDefinition)
    {
        $definition = new Definition($class);
        if (is_array($yiiDefinition)) {
            if (isset($yiiDefinition['definition'])) {
                $definition = new CallableDefinition();
                $definition->setLazy(true);
                $definition->setClosure($yiiDefinition['definition']);
                return $definition;
            }
            $arguments = $yiiDefinition['__construct()'] ?? [];
            $arguments = $this->processArguments($arguments);
            $class = $yiiDefinition['class'] ?? $class;

            $definition->setClass($class);
            $definition->setArguments($arguments);
        } else if (is_callable($yiiDefinition)) {
            $definition = new CallableDefinition();
            $definition->setLazy(true);
            $definition->setClosure($yiiDefinition);
            return $definition;
        } elseif (is_object($yiiDefinition)) {
            $definition = new InlineDefinition();
            $definition->setClass($class);
            $definition->setLazy(true);
            $definition->setObject($yiiDefinition);
            return $definition;
        } else if (is_string($yiiDefinition) && class_exists($yiiDefinition)) {
            $definition->setClass($yiiDefinition);
            return $definition;

            return $definition1;
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
            if ($key === 'App\\Blog\\PostRepository') {
                $var = true;
            }
            if (!is_numeric($key)) {
                $result['$' . $key] = $this->processArgument($argument);
            } else {
                $result[] = $this->processArgument($argument);
            }
        }
        return $result;
    }

    private function processArgument(mixed $argument)
    {
        if ($argument instanceof Closure) {
            $definition = new CallableDefinition();
            $definition->setLazy(true);
            $definition->setClosure($argument);
//            dd($argument);
            return $argument;
        }
//        if (is_string($argument)) {
//            return new Reference($argument);
//        }
        if ($argument instanceof \Yiisoft\Factory\Definition\Reference) {
            $id = $argument->getId();
            return new Reference($id);
        }

        if ($argument instanceof DynamicReference) {
            $ref = new ReflectionObject($argument);
            $def = $ref->getProperty('definition');
            $def->setAccessible(true);
            /* @var DefinitionInterface $val */
            $val = $def->getValue($argument);
            return $this->processArgument($val);
        }

        if ($argument instanceof ArrayDefinition) {
//            dd($argument);
            return new Definition(
                $argument->getClass(),
                $this->processArguments($argument->getConstructorArguments())
            );
        }

        if ($argument instanceof \Yiisoft\Factory\Definition\CallableDefinition) {
            $ref = new ReflectionObject($argument);
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

        if (is_object($argument)) {
            $definition = new CallableDefinition(get_class($argument));
            $definition->setLazy(true);
            $definition->setClosure($argument);
            $serviceId = get_class($argument) . spl_object_id($argument);
//            dd($argument);
//            $inline = new InlineServiceConfigurator($definition);
//            $inline->args([444]);
            $definition = new Expression(sprintf(
                'object("%s")',
                preg_quote(serialize($argument), '"')
            ));
//            self::$staticArguments[$serviceId] = $definition;

            return $definition;
        }

        return $argument;
    }

    private function loadThirdPartyServices(ServicesConfigurator $serviceConfigurator): void
    {
        $configs = [
            [
                'namespace' => 'App\\',
                'path' => 'src/',
            ],
            [
                'namespace' => 'Yiisoft\\Access\\',
                'path' => 'vendor/yiisoft/access/src/',
            ],
            [
                'namespace' => 'Yiisoft\\Csrf\\',
                'path' => 'vendor/yiisoft/csrf/src/',
            ],
            [
                'namespace' => 'Yiisoft\\DataResponse\\',
                'path' => 'vendor/yiisoft/data-response/src/',
            ],
            [
                'namespace' => 'Yiisoft\\User\\',
                'path' => 'vendor/yiisoft/user/src/',
            ],
//            [
//                'namespace' => 'Cycle\\ORM\\',
//                'path' => 'vendor/cycle/orm/src/',
//            ],
        ];
        foreach ($configs as $config) {
            $serviceConfigurator
                ->load($config['namespace'], sprintf('../%s/*', $config['path']))
                ->autoconfigure(true)
                ->autowire(true);
        }
    }

    private function ignoreNonServices(ServicesConfigurator $serviceConfigurator)
    {
        $configs = [
            'App\Blog\PostStatus',
            'App\CallableInitiator',
            'App\User\User',
            'App\InlineDefinition',
            'Yiisoft\DataResponse\DataResponse',
            'Yiisoft\User\Event\AfterLogin',
            'Yiisoft\User\Event\BeforeLogin',
            'Yiisoft\User\Event\AfterLogout',
            'Yiisoft\User\Event\BeforeLogout',
            'Yiisoft\User\Login\Cookie\CookieLogin',
            'Yiisoft\User\Login\Cookie\CookieLoginMiddleware',
        ];
        foreach ($configs as $config) {
            $serviceConfigurator
                ->remove($config);
        }
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

class MyContainerBuilder extends ContainerBuilder
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    public function get(string $id, int $invalidBehavior = SymfonyContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        try {
            return parent::get($id, $invalidBehavior);
        } catch (Throwable $e) {
            return $this->container->get($id);
        }
    }
}

class ContainerConfigProxy extends Container
{
    private ContainerBuilder $containerBuilder;
    private $services = [];

    public function __construct(ContainerBuilder $containerBuilder)
    {
        $this->containerBuilder = $containerBuilder;
    }

    public function get($id)
    {
        return $this->containerBuilder->get($id);
    }

    public function has($id): bool
    {
        return $this->containerBuilder->has($id);
    }

    public function set(string $id, $service): void
    {
        if ($id === 'Psr\\Container\\ContainerInterface') {
            return;
        }
        if (is_object($service)) {
            $this->services[$id] = $service;
            $definition = new SyntenthicDefinition($id);
            $definition->setSynthetic(true);
            $v = $this->containerBuilder->hasDefinition($id);
            $this->containerBuilder->setDefinition($id, $definition);
            return;
        }

        throw new RuntimeException('Not object config ' . $id);
    }

    public function injectServices(): void
    {
        foreach ($this->services as $id => $service) {
            $this->containerBuilder->set($id, $service);
        }
    }

    /**
     * @return ContainerBuilder
     */
    public function getContainerBuilder(): ContainerBuilder
    {
        return $this->containerBuilder;
    }
}
