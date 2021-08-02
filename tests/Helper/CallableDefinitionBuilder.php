<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use App\CallableDefinition;

class CallableDefinitionBuilder extends SymfonyDefinitionBuilder
{
    public function __construct()
    {
        $this->definition = new CallableDefinition();
    }

    public function withCallable(callable $param)
    {
        $new = clone $this;
        $new->definition->setClosure($param);
        return $new;
    }
}
