<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Definition;

class InlineDefinition extends Definition
{
    private object $object;

    public function setObject(object $object)
    {
        $this->object = $object;
    }

    public function getObject(): object
    {
        return $this->object;
    }
}
