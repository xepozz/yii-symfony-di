<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use Symfony\Component\DependencyInjection\Definition;

class SymfonyDefinitionBuilder
{
    protected Definition $definition;

    private function __construct()
    {
        $this->definition = new Definition();
        $this->definition->setPublic(true);
        $this->definition->setAutowired(true);
        $this->definition->setAutoconfigured(true);
    }

    public static function new()
    {
        return new static();
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
        if (!$lazy) {
            $new->definition->setChanges(
                array_diff_assoc(
                    $new->definition->getChanges(),
                    ['lazy' => true]
                )
            );
        }
        return $new;
    }

    public function withArguments(...$arguments)
    {
        $new = clone $this;
        $new->definition->setArguments($arguments);
        return $new;
    }

    public function withPublic(bool $public)
    {
        $new = clone $this;
        $new->definition->setPublic($public);
        if (!$public) {
            $new->definition->setChanges(
                array_diff_assoc(
                    $new->definition->getChanges(),
                    ['public' => true]
                )
            );
        }
        return $new;
    }

    public function withAutoconfigured(bool $autoconfigured)
    {
        $new = clone $this;
        $new->definition->setAutoconfigured($autoconfigured);
        if (!$autoconfigured) {
            $new->definition->setChanges(
                array_diff_assoc(
                    $new->definition->getChanges(),
                    ['autoconfigured' => true]
                )
            );
        }
        return $new;
    }

    public function withAutowired(bool $autowired)
    {
        $new = clone $this;
        $new->definition->setAutowired($autowired);
        if (!$autowired) {
            $new->definition->setChanges(
                array_diff_assoc(
                    $new->definition->getChanges(),
                    ['autowired' => true]
                )
            );
        }
        return $new;
    }

    public function withMethodCall(string $methodName, $value)
    {
        $new = clone $this;
        $new->definition->addMethodCall($methodName, [$value]);
        return $new;
    }

    public function withPropertySet(string $propertyName, $value)
    {
        $new = clone $this;
        $new->definition->setProperty($propertyName, $value);
        return $new;
    }


    public function build()
    {
        return $this->definition;
    }

}
