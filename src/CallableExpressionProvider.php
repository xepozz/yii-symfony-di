<?php


namespace App;

use Opis\Closure\SerializableClosure;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Yiisoft\Injector\Injector;

class CallableExpressionProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return [
            new ExpressionFunction('closure',
                function ($arg) {
                    return $arg;
                }, function (array $variables, $value) {
                    $c = unserialize($value);
                    $injector = new Injector($variables['container']);
                    return $injector->invoke($c);
                }),
        ];
    }
}
