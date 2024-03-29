<?php
declare(strict_types=1);

namespace LessAbstractService\Permission\Http\AuthorizationConstraint;

use LessAbstractService\Permission\Model\Permission;

final class HasReadPermissionAuthorization extends AbstractPermissionAuthorization
{
    protected function hasPermissionFlag(Permission $permission): bool
    {
        return $permission->attributes->flags->read;
    }
}
