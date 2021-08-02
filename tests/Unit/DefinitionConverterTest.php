<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\DefinitionConverter;
use App\Tests\Helper\SymfonyAliasBuilder;
use App\Tests\Helper\SymfonyDefinitionBuilder;
use App\Tests\Stub\FlexibleWithOptionalParameterInConstructorStub;
use App\Tests\Stub\InterfaceStub;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This test checks only right definition format
 */
final class DefinitionConverterTest extends DefinitionConverterTestCase
{
    /**
     * @dataProvider convertDataProvider
     */
    public function testConvert(array $definitions, array $expectedDefinitions): void
    {
        $containerBuilder = $this->createContainerBuilder();
        $wrapper = new DefinitionConverter($containerBuilder);
        $result = $wrapper->parse($definitions);

        $this->assertEquals($expectedDefinitions, $result);
    }

    public function convertDataProvider(): array
    {
        return array_merge(parent::convertDataProvider(), [
            'interface and reference' => [
                [
                    InterfaceStub::class => FlexibleWithOptionalParameterInConstructorStub::class,
                    FlexibleWithOptionalParameterInConstructorStub::class => [
                        'class' => FlexibleWithOptionalParameterInConstructorStub::class,
                    ],
                ],
                [
                    InterfaceStub::class => SymfonyAliasBuilder::new(FlexibleWithOptionalParameterInConstructorStub::class)
                        ->withPublic(true)
                        ->build(),
                    FlexibleWithOptionalParameterInConstructorStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleWithOptionalParameterInConstructorStub::class)
                        ->build(),
                ],
            ],
        ]);
    }

    public function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(null);
    }
}
