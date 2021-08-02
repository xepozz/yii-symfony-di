<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\CallableDefinition;
use App\CallableExpressionProvider;
use App\CallableInitiator;
use App\DefinitionConverter;
use App\ObjectExpressionProvider;
use App\Tests\Helper\CallableDefinitionBuilder;
use App\Tests\Helper\SymfonyDefinitionBuilder;
use App\Tests\Stub\FlexibleStub;
use App\Tests\Stub\ScalarStub;
use Opis\Closure\SerializableClosure;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition as SymfonyDefinition;
use Symfony\Component\DependencyInjection\Reference as SymfonyReference;
use Yiisoft\Factory\Definition\DynamicReference;
use Yiisoft\Factory\Definition\Reference;

abstract class DefinitionConverterTestCase extends TestCase
{
    /**
     * @dataProvider convertDataProvider
     */
    abstract public function testConvert(array $definitions, array $expected): void;

    public function convertDataProvider(): array
    {
        return [
            'simple' => [
                [
                    FlexibleStub::class =>  FlexibleStub::class,
                ],
                [
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->build(),
                ],
            ],
            'simple with __construct()' => [
                [
                    FlexibleStub::class => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            12345,
                        ],
                    ],
                ],
                [
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(12345)
                        ->build(),
                ],
            ],
            'simple with methods' => [
                [
                    FlexibleStub::class => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            12345,
                        ],
                        'setParam()' => [555]
                    ],
                ],
                [
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(12345)
                        ->withMethodCall('setParam', 555)
                        ->build(),
                ],
            ],
            'aliases' => [
                [
                    'alias_for_flexible' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            12345,
                        ],
                    ],
                ],
                [
                    'alias_for_flexible' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(12345)
                        ->build(),
                ],
            ],
            'different argument types' => [
                [
                    'int' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            12345,
                        ],
                    ],
                    'string' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            'string',
                        ],
                    ],
                    'float' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            10.12345,
                        ],
                    ],
                    'null' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            null,
                        ],
                    ],
                    'bool' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            false,
                        ],
                    ],
                    'array' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            ['key' => 'value'],
                        ],
                    ],
                ],
                [
                    'int' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(12345)
                        ->build(),
                    'string' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments('string')
                        ->build(),
                    'float' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(10.12345)
                        ->build(),
                    'null' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(null)
                        ->build(),
                    'bool' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(false)
                        ->build(),
                    'array' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(['key' => 'value'])
                        ->build(),
                ],
            ],
            'multiple arguments' => [
                [
                    ScalarStub::class => [
                        'class' => ScalarStub::class,
                        '__construct()' => [
                            'Dmitry',
                            100,
                            100.5,
                            null,
                        ],
                    ],
                ],
                [
                    ScalarStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(ScalarStub::class)
                        ->withArguments(
                            'Dmitry',
                            100,
                            100.5,
                            null
                        )
                        ->build(),
                ],
            ],
            'the same object' => [
                [
                    'alias_for_flexible' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            56789,
                        ],
                    ],
                    FlexibleStub::class => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            12345,
                        ],
                    ],
                ],
                [
                    'alias_for_flexible' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(56789)
                        ->build(),
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(12345)
                        ->build(),
                ],
            ],
            'parse dynamic definition' => [
                [
                    FlexibleStub::class => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            DynamicReference::to([
                                'class' => FlexibleStub::class,
                                '__construct()' => [
                                    12345,
                                ],
                            ]),
                        ],
                    ],
                ],
                [
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(
                            SymfonyDefinitionBuilder::new()
                                ->withClass(FlexibleStub::class)
                                ->withArguments(12345)
                                ->withPublic(false)
                                ->withAutoconfigured(false)
                                ->withAutowired(false)
                                ->build()
                        )
                        ->build(),
                ],
            ],
            'parse reference' => [
                [
                    FlexibleStub::class => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            Reference::to('another_class'),
                        ],
                    ],
                    'another_class' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            true,
                        ],
                    ],
                ],
                [
                    FlexibleStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(
                            new SymfonyReference('another_class')
                        )
                        ->build(),
                    'another_class' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(
                            true,
                        )
                        ->build(),
                ],
            ],
            'parse callable argument' => [
                [
                    'alias' => [
                        'class' => FlexibleStub::class,
                        '__construct()' => [
                            fn() => new FlexibleStub(null),
                        ],
                    ],
                ],
                [
                    'alias' => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleStub::class)
                        ->withArguments(
                            fn() => new FlexibleStub(null)
                        )
                        ->build(),
                ],
            ],
            'parse callable definition' => [
                [
                    'alias' => [
                        'class' => FlexibleStub::class,
                        'definition' => fn() => new FlexibleStub(null),
                    ],
                ],
                [
                    'alias' => CallableDefinitionBuilder::new()
                        ->withCallable(fn() => new FlexibleStub(null))
                        ->withClass(FlexibleStub::class)
                        ->build(),
                ],
            ],
        ];
    }
}
