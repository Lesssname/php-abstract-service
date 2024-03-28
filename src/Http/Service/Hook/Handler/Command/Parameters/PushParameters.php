<?php
declare(strict_types=1);

namespace LessAbstractService\Http\Service\Hook\Handler\Command\Parameters;

use LessValueObject\Composite\AbstractCompositeValueObject;

/**
 * @psalm-immutable
 *
 * @deprecated
 */
final class PushParameters extends AbstractCompositeValueObject
{
    /**
     * @param array<mixed> $body
     */
    public function __construct(
        public readonly Push\Type $type,
        public readonly array $body,
    ) {}
}
