<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Definition;

class CallableDefinition extends Definition
{
    public function __construct(string $class = null, array $arguments = [])
    {
        parent::__construct($class, $arguments);
        $this->setLazy(true);
        $this->setPublic(true);
    }

    /**
     * @var callable
     */
    private $closure;

    public function setClosure( $closure)
    {
        $this->closure = $closure;
    }

    public function getClosure()
    {
        return $this->closure;
    }
}
