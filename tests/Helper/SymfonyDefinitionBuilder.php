<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use Symfony\Component\DependencyInjection\Definition;

class SymfonyDefinitionBuilder
{
    private Definition $definition;

    private function __construct()
    {
        $this->definition = new Definition();
        $this->definition->setPublic(true);
        $this->definition->setAutowired(true);
        $this->definition->setAutoconfigured(true);
    }

    public static function new()
    {
        return new self();
    }

    public function withClass($class)
    {
        $new = clone $this;
        $new->definition->setClass($class);
        return $new;
    }

    public function withLazy(bool $lazy)
    {
        $new = clone $this;
        $new->definition->setLazy($lazy);
        return $new;
    }

    public function withArguments(...$arguments)
    {
        $new = clone $this;
        $new->definition->setArguments($arguments);
        return $new;
    }

    public function build()
    {
        return $this->definition;
    }

}
