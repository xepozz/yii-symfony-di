<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\DefinitionConverter;
use App\Tests\Helper\SymfonyDefinitionBuilder;
use App\Tests\Stub\FlexibleStub;
use App\Tests\Stub\ScalarStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition as SymfonyDefinition;

class ConverterTest extends TestCase
{
    /**
     * @dataProvider convertDataProvider
     */
    public function testConvert(array $definitions, array $expected)
    {
        $providers = [];
        $containerBuilder = $this->createContainerBuilder();
        $wrapper = new DefinitionConverter($containerBuilder);
        $wrapper->wrap($definitions, $providers);
        $result = $this->extractDefinitions($wrapper);
//        var_dump($result);
        $this->assertEquals($expected, $result);
    }

    public function convertDataProvider()
    {
        return [
            [
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
            [
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
            [
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
            [
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

        ];
    }

    public function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(null);
    }

    private function extractDefinitions(DefinitionConverter $wrapper): array
    {
        $definitions = $wrapper->containerBuilder->getDefinitions();
        unset($definitions['service_container']);
        return $definitions;
    }
}
