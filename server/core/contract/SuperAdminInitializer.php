<?php

declare(strict_types=1);

namespace core\contract;

interface SuperAdminInitializer
{
    /** @return array{created: bool, id: int, username: string} */
    public function initSuperAdmin(string $username, string $password, ?string $email, ?string $nickname): array;
}
