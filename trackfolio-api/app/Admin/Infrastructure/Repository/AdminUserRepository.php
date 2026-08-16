<?php

namespace App\Admin\Infrastructure\Repository;

use App\Admin\Domain\Entity\AdminUser;

class AdminUserRepository
{
    public function existsByUserId(int $userId): bool
    {
        return AdminUser::query()
            ->where('user_id', $userId)
            ->exists();
    }
}
