<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use Symfony\Component\DependencyInjection\Alias;

class SymfonyAliasBuilder
{
    protected Alias $alias;

    private function __construct(string $id)
    {
        $this->alias = new Alias($id);
    }

    public static function new(string $id)
    {
        return new static($id);
    }

    public function withPublic(bool $public)
    {
        $new = clone $this;
        $new->alias->setPublic($public);
        return $new;
    }

    public function build()
    {
        return $this->alias;
    }

}
