<?php

namespace App\Support\Dcs\Signing;

/**
 * One box of a version's signature sheet, in signing order (`order` starts at 1).
 */
final class SignatureColumn
{
    public function __construct(
        public readonly int $order,
        public readonly string $label,
        public readonly ?int $roleId,
        public readonly ?string $roleKey,
        public readonly ?string $roleName,
        public readonly ?int $assignedUserId,
        public readonly ?string $groupHeading,
        public readonly int $groupPosition,
    ) {}
}
