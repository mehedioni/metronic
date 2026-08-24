<?php

namespace Modules\Access\Exceptions;

use RuntimeException;

/**
 * Raised when an actor tries to grant a role or permission they do not hold
 * themselves — the privilege-escalation path that permission checks alone do
 * not close.
 */
class RoleEscalationException extends RuntimeException
{
    public static function forRole(string $role): self
    {
        return new self("You cannot grant the \"{$role}\" role because you do not hold all of its permissions.");
    }

    public static function forPermission(string $permission): self
    {
        return new self("You cannot grant the \"{$permission}\" permission because you do not hold it.");
    }
}
