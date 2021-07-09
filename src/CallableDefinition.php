<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Definition;

class CallableDefinition extends Definition
{
    /**
     * @var callable
     */
    private $closure;

    public function setClosure(callable $closure)
    {
        $this->closure = $closure;
    }

    public function getClosure(): callable
    {
        return $this->closure;
    }
}
