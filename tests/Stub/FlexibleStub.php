<?php
declare(strict_types=1);

namespace App\Tests\Stub;

class FlexibleStub
{
    private $param;

    public function __construct($param)
    {
        $this->param = $param;
    }
}
