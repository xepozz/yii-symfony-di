<?php
declare(strict_types=1);

namespace App;

use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Yiisoft\Di\Container;

class YiiContainerProxy extends Container
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
