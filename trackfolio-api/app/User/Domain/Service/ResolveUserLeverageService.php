<?php

namespace App\User\Domain\Service;

use App\User\Domain\Entity\UserLeverage;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ResolveUserLeverageService
{
    /**
     * Latest leverage snapshot for the user (current).
     */
    public function currentAmountEurMinUnit(int $userId): int
    {
        return $this->amountEurMinUnitAt($userId, Carbon::now());
    }

    /**
     * Leverage effective at a given moment: last row with recorded_at <= $at.
     */
    public function amountEurMinUnitAt(int $userId, CarbonInterface $at): int
    {
        $row = UserLeverage::query()
            ->where('user_id', $userId)
            ->where('recorded_at', '<=', $at)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        return $row !== null ? (int) $row->amount_eur_min_unit : 0;
    }

    public function record(int $userId, int $amountEurMinUnit, ?CarbonInterface $recordedAt = null): UserLeverage
    {
        return UserLeverage::query()->create([
            'user_id' => $userId,
            'amount_eur_min_unit' => max(0, $amountEurMinUnit),
            'recorded_at' => $recordedAt ?? Carbon::now(),
        ]);
    }
}
