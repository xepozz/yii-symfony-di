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


        $this->loadThirdPartyServices($serviceConfigurator);
        $this->ignoreNonServices($serviceConfigurator);


        $definitionConverter = new DefinitionConverter($containerBuilder);
        $proxy = $definitionConverter->wrap($yiiDefinitions, $yiiProviders);

        $isDebug = false;
        $file = __DIR__ . '/../cache/container.php';
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
