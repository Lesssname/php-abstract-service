<?php
declare(strict_types=1);

namespace LessAbstractService\Http\Handler\Event\Response;

use LessValueObject\Composite\AbstractCompositeValueObject;
use LessValueObject\String\Format\Resource\Identifier;

/**
 * @psalm-immutable
 *
 * @deprecated use Resource namespaced
 */
final class CreatedResponse extends AbstractCompositeValueObject
{
    public function __construct(public readonly Identifier $id)
    {}
}
