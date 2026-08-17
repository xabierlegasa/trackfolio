<?php

namespace App\Admin\Domain\Service;

use App\User\Domain\Entity\User;

class IsAdminUserService
{
    public function execute(int $userId): bool
    {
        return (bool) User::query()
            ->whereKey($userId)
            ->value('is_admin');
    }
}
