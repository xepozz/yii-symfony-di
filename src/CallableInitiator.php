<?php
declare(strict_types=1);

namespace App;

use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;
use Yiisoft\Injector\Injector;

class CallableInitiator implements InstantiatorInterface
{
    private RuntimeInstantiator $runtimeInstantiator;

    public function __construct(RuntimeInstantiator $runtimeInstantiator)
    {
        $this->runtimeInstantiator = $runtimeInstantiator;

    }

    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator)
    {
        if ($definition instanceof CallableDefinition) {
            $injector = new Injector($container);
            return $injector->invoke($definition->getClosure());
        }

        return $this->runtimeInstantiator->instantiateProxy($container, $definition, $id, $realInstantiator);
    }
}
