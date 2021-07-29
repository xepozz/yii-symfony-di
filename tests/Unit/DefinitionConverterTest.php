<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\DefinitionConverter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This test checks only right definition format
 */
final class DefinitionConverterTest extends DefinitionConverterTestCase
{
    /**
     * @dataProvider convertDataProvider
     */
    public function testConvert(array $definitions, array $expected): void
    {
        $containerBuilder = $this->createContainerBuilder();
        $wrapper = new DefinitionConverter($containerBuilder);
        $result = $wrapper->parse($definitions);

        $this->assertEquals($expected, $result);
    }

    public function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(null);
    }
}
