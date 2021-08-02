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

    // temporary it can be null
    public function __construct(?ContainerBuilder $containerBuilder)
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
        $proxy = new YiiContainerProxy($this->containerBuilder);
        foreach ($yiiProviders as $yiiProvider) {
            /* @var ServiceProviderInterface $provider */
            $provider = new $yiiProvider;
            $provider->register($proxy);
        }
////        var_dump($symfonyDefenitions);
        $this->containerBuilder->compile();
//        dd($this->containerBuilder->getDefinitions());
//        $loader = new PhpFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/../config'));
//        $loader->load('services.php');
//        $s = $this->containerBuilder->get(RouteCollectionInterface::class);
//        $s = $this->containerBuilder->get(Stub1::class);
//        dd($s);

        return $proxy;
    }

    public function parse(array $yiiDefinitions): array
    {
        $definitions = [];
        foreach ($yiiDefinitions as $class => $yiiDefinition) {
            $definition = $this->creatDefinition($class, $yiiDefinition);
            $definitions[$class] = $definition;
        }
        return $definitions;
    }

    private function creatDefinition(string $alias, $yiiDefinition)
    {
        if (is_callable($yiiDefinition)) {
            $definition = new CallableDefinition($alias);
            $definition->setClosure(($yiiDefinition));
            return $definition;
        }
        if (is_object($yiiDefinition)) {
            $definition = new InlineDefinition($alias);
            $definition->setLazy(true);
            $definition->setObject($yiiDefinition);
            return $definition;
        }

        if (is_string($yiiDefinition) && class_exists($yiiDefinition)) {
            $definition = new Definition($yiiDefinition);
        } elseif (is_array($yiiDefinition)) {
            $definition = new Definition($alias);
            if (isset($yiiDefinition['definition'])) {
                $definition = $this->creatDefinition($alias, $yiiDefinition['definition']);
            }
            foreach ($yiiDefinition as $key => $value) {
                switch (true) {
                    case $key === 'class':
                        if ($value !== $alias) {
                            $definition->setClass($value);
                        }
                        break;
                    case $key === '__construct()':
                        $arguments = $this->processArguments($value);
                        $definition->setArguments($arguments);
                        break;
                    case str_ends_with($key, '()'):
                        $definition->addMethodCall(substr($key, 0, -2), $value);
                        break;
                    case str_starts_with($key, '$'):
                        $definition->setProperty(substr($key, 1), $value);
                        break;
                }
            }
        }

        $definition->setPublic(true);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);

        return $definition;
    }

    private function processArguments(array $arguments): array
    {
        $result = [];
        foreach ($arguments as $key => $argument) {
            $processedArgument = $this->processArgument($argument);

            if (!is_numeric($key)) {
                $result['$' . $key] = $processedArgument;
            } else {
                $result[] = $processedArgument;
            }
        }
        return $result;
    }

    private function processArgument(mixed $argument)
    {
        if ($argument instanceof Closure) {
            $definition = new CallableDefinition();
            $definition->setClosure($argument);
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
