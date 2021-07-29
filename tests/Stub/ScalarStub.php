<?php
declare(strict_types=1);

namespace App\Tests\Stub;

class ScalarStub
{
    private string $name;
    private int $age;
    private float $weight;
    private ?string $nullableString;

    public function __construct(string $name, int $age, float $weight, ?string $nullableString)
    {

        $this->name = $name;
        $this->age = $age;
        $this->weight = $weight;
        $this->nullableString = $nullableString;
    }
}
