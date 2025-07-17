<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayShapeDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;

class ArrayShapeDenormalizerTest extends TestCase
{
    private ArrayShapeDenormalizer $denormalizer;
    private MockObject&DenormalizerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(DenormalizerInterface::class);
        $this->denormalizer = new ArrayShapeDenormalizer();
        $this->denormalizer->setDenormalizer($this->serializer);
    }

    public function testDenormalize()
    {
        $denormalizerCalls = [
            [['baz'], 'baz'],
            [[['foo' => 'one', 'bar' => [2, 3]]], new ArrayShapeDummy(foo: 'one', bar: [2, 3])],
        ];

        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(function ($data) use (&$denormalizerCalls) {
                [$expectedArgs, $return] = array_shift($denormalizerCalls);
                $this->assertSame($expectedArgs, [$data]);

                return $return;
            })
        ;

        $result = $this->denormalizer->denormalize(
            [
                'foo' => ['foo' => 'one', 'bar' => [2, 3]],
                'bar' => 'baz',
            ],
            \sprintf('array{foo: %s, bar: string}', __NAMESPACE__.'\ArrayShapeDummy'),
        );

        $this->assertEquals(
            [
                'foo' => new ArrayShapeDummy('one', [2, 3]),
                'bar' => 'baz',
            ],
            $result
        );
    }

    public function testDenormalizeWithMissingMandatoryKey()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->denormalizer->denormalize([0 => 123], 'array{0: int, bar: string}');
    }

    public function testDenormalizeWithMissingOptionalKey()
    {
        $this->serializer->expects($this->once())
            ->method('denormalize')
            ->willReturn(123)
        ;

        $this->assertSame([0 => 123], $this->denormalizer->denormalize([0 => 123], 'array{0: int, bar?: string}'));
    }

    public function testDenormalizeExtraItems()
    {
        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturn(123)
        ;

        $this->assertSame([0 => 123, 99 => 123], $this->denormalizer->denormalize([0 => 123, 99 => 123], 'array{0: int, ...<int, string>}'));
    }

    public function testDenormalizeExtraItemsKeyTypeMismatch()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->denormalizer->denormalize(['foo' => 123], 'array{...<int, string>}');
    }

    public function testSupportsValidArray()
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization(
                [
                    'foo' => ['foo' => 'one', 'bar' => [2, 3]],
                    'bar' => 'baz',
                ],
                \sprintf('array{foo: %s, bar: string}', ArrayShapeDummy::class),
                'json',
                ['con' => 'text'],
            )
        );
    }

    public function testShapeNotCheckedInSupportsCall()
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization([], 'array{bar: string}'));
    }

    public function testSupportsNoArray()
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization(
                ['foo' => 'one', 'bar' => [1, 2]],
                ArrayShapeDummy::class
            )
        );
    }

    public function testDenormalizeWithoutDenormalizer()
    {
        $arrayShapeDenormalizer = new ArrayShapeDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayShapeDenormalizer->denormalize([0 => 'foo'], 'array{0: string}');
    }

    public function testSupportsDenormalizationWithoutDenormalizer()
    {
        $arrayShapeDenormalizer = new ArrayShapeDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayShapeDenormalizer->supportsDenormalization([], 'array{foo: string}');
    }
}

class ArrayShapeDummy
{
    /**
     * @param array{0: int, 1: int} $bar
     */
    public function __construct(public string $foo, public array $bar)
    {
    }
}
