<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\CallableExpressionProvider;
use App\CallableInitiator;
use App\DefinitionConverter;
use App\ObjectExpressionProvider;
use App\Tests\Helper\SymfonyAliasBuilder;
use App\Tests\Helper\SymfonyDefinitionBuilder;
use App\Tests\Stub\FlexibleWithOptionalParameterInConstructorStub;
use App\Tests\Stub\InterfaceStub;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This test check full integration
 */
final class ConverterTest extends DefinitionConverterTestCase
{
    /**
     * @dataProvider convertDataProvider
     */
    public function testConvert(array $definitions, array $expectedDefinitions, array $expectedAliases = []): void
    {
        $providers = [];
        $containerBuilder = $this->createContainerBuilder();
        $wrapper = new DefinitionConverter($containerBuilder);
        $wrapper->wrap($definitions, $providers);
        $actualDefinitions = $this->extractDefinitions($wrapper);
        $actualAliases = $this->extractAliases($wrapper);

        $this->assertEquals($expectedDefinitions, $actualDefinitions);
        $this->assertEquals($expectedAliases, $actualAliases);
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
                    FlexibleWithOptionalParameterInConstructorStub::class => SymfonyDefinitionBuilder::new()
                        ->withClass(FlexibleWithOptionalParameterInConstructorStub::class)
                        ->build(),
                ],
                [
                    InterfaceStub::class => SymfonyAliasBuilder::new(FlexibleWithOptionalParameterInConstructorStub::class)
                        ->withPublic(true)
                        ->build(),
                ],
            ],
        ]);
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder(null);
        $containerBuilder->setProxyInstantiator(new CallableInitiator(new RuntimeInstantiator()));
        $containerBuilder->addExpressionLanguageProvider(new CallableExpressionProvider());
        $containerBuilder->addExpressionLanguageProvider(new ObjectExpressionProvider());
        return $containerBuilder;
    }

    private function extractDefinitions(DefinitionConverter $wrapper): array
    {
        $definitions = $wrapper->containerBuilder->getDefinitions();
        unset($definitions['service_container']);
        return $definitions;
    }

    private function extractAliases(DefinitionConverter $wrapper): array
    {
        return $wrapper->containerBuilder->getAliases();
    }
}
