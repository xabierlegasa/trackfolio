<?php

namespace App\Admin\Domain\Service;

use App\Admin\Infrastructure\Repository\AdminUserRepository;

class IsAdminUserService
{
    public function __construct(
        private AdminUserRepository $adminUserRepository,
    ) {}

    public function execute(int $userId): bool
    {
        return $this->adminUserRepository->existsByUserId($userId);
    }
}
