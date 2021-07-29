<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use App\CallableDefinition;
use Symfony\Component\DependencyInjection\Definition;

class CallableDefinitionBuilder
{
    private Definition $definition;

    private function __construct()
    {
        $this->definition = new CallableDefinition();
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

    public function withCallable(callable $param)
    {
        $new = clone $this;
        $new->definition->setClosure($param);
        return $new;
    }

    public function build()
    {
        $this->definition->setChanges([]);
        return $this->definition;
    }
}
