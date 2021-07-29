<?php
declare(strict_types=1);

namespace App;

use Closure;
use Opis\Closure\SerializableClosure;
use ReflectionObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Yiisoft\Di\Contracts\ServiceProviderInterface;
use Yiisoft\Factory\Definition\ArrayDefinition;
use Yiisoft\Factory\Definition\DefinitionInterface;
use Yiisoft\Factory\Definition\DynamicReference;

class DefinitionConverter
{
    public ContainerBuilder $containerBuilder;

    public function __construct(ContainerBuilder $containerBuilder)
    {
        $this->containerBuilder = $containerBuilder;
    }

    public function wrap(array $yiiDefinitions, array $yiiProviders)
    {
        foreach ($yiiDefinitions as $class => $yiiDefinition) {
            $definition = $this->creatDefinition($class, $yiiDefinition);
            if ($definition instanceof Definition) {
                $this->containerBuilder->setDefinition($class, $definition);
            } elseif ($definition instanceof Reference) {
                $this->containerBuilder->set($class, $definition);
            } else {
                $this->containerBuilder->setDefinition($class, $definition);
                $this->containerBuilder->set($class, $definition);
            }
        }
//        $proxy = new ContainerConfigProxy($this->containerBuilder);
//        foreach ($yiiProviders as $yiiProvider) {
//            /* @var ServiceProviderInterface $provider */
//            $provider = new $yiiProvider;
//            $provider->register($proxy);
//        }
//        var_dump($symfonyDefenitions);
        $this->containerBuilder->compile();
//        dd($this->containerBuilder->getDefinitions());
//        $loader = new PhpFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/../config'));
//        $loader->load('services.php');
//        $s = $this->containerBuilder->get(RouteCollectionInterface::class);
//        $s = $this->containerBuilder->get(Stub1::class);
//        dd($s);

//        return $proxy;
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

}
