<?php
declare(strict_types=1);

namespace App\Tests\Stub;

class FlexibleWithOptionalParameterInConstructorStub implements InterfaceStub
{
    private $param;
    public $public;

    public function __construct($param = null)
    {
        $this->param = $param;
    }

    public function setParam($value): void
    {
        $this->param = $value;
    }

    public function setPublic($public): void
    {
        $this->public = $public;
    }
}
