<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Denormalizes array shapes. Depends on symfony/type-info package.
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ArrayShapeDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private readonly TypeResolverInterface $typeResolver;

    public function __construct(?TypeResolverInterface $typeResolver = null)
    {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): array
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException(\sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        $shape = $this->typeResolver->resolve($type);
        if (!$shape instanceof ArrayShapeType) {
            throw new InvalidArgumentException(\sprintf('The type "%s" is not an array shape.', $type));
        }

        $keysToDenormalize = [];
        $denormalized = [];
        foreach ($shape->getShape() as $key => $item) {
            // Quotes in string keys are optional, we need to trim them out when they exist.
            if (is_string($key) && strlen($key) > 1 && in_array($key[0] ?? '', ['"', "'"]) && str_ends_with($key, $key[0])) {
                $key = substr($key, 1, -1);
            }

            if (!array_key_exists($key, $data)) {
                if (!$item['optional']) {
                    throw new NotNormalizableValueException(\sprintf('The key "%s" must exists.', $key));
                }

                continue;
            }

            $keysToDenormalize[$key] = $item['type'];
        }

        if (!$shape->isSealed() && $extraData = array_diff_key($data, $keysToDenormalize)) {
            $extraKeyType = $shape->getExtraKeyType();
            $extraValueType = $shape->getExtraValueType();

            foreach ($extraData as $key => $value) {
                if (!$extraKeyType->accepts($key)) {
                    throw new NotNormalizableValueException(\sprintf('The array shape only accepts "%s" extra keys.', $extraKeyType));
                }

                $keysToDenormalize[$key] = $extraValueType;
            }
        }

        foreach ($keysToDenormalize as $key => $type) {
            $subContext = $context;
            $subContext['deserialization_path'] = ($context['deserialization_path'] ?? false) ? \sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            $denormalized[$key] = $this->denormalizer->denormalize($data[$key], (string) $type, $format, $subContext);
            unset($data[$key]);
        }

        return $denormalized;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException(\sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        return str_starts_with($type, 'array{') && str_ends_with($type, '}');
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => null, '*' => true];
    }
}
