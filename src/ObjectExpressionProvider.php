<?php


namespace App;

use Opis\Closure\SerializableClosure;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Yiisoft\Injector\Injector;

class ObjectExpressionProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return [
            new ExpressionFunction('object',
                function ($arg) {
                    return ($arg);
                }, function (array $variables, $value) {
                    return unserialize($value);
                    $injector = new Injector($variables['container']);
                    return $injector->invoke($c);
                }),
        ];
    }
}
