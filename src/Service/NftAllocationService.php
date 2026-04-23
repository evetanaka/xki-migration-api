<?php

namespace App\Service;

class NftAllocationService
{
    private const MULTIPLIERS = [
        'Common' => 0,      // Fixed amount, not points-based
        'Uncommon' => 3,
        'Rare' => 8,
        'Epic' => 25,
        'Legendary' => 100,
    ];

    private const COMMON_FIXED = 10; // $XKI per Common NFT

    /**
     * Calculate allocation per NFT given scarcity counts and pool size.
     *
     * @param array<string, int> $scarcityCounts ['Common' => 30802, 'Uncommon' => 1172, ...]
     * @param float $poolTotal Total $XKI pool
     * @return array<string, float> ['Common' => 10, 'Uncommon' => 912.60, ...]
     */
    public function calculateAllocations(array $scarcityCounts, float $poolTotal = 5_000_000): array
    {
        $commonCount = $scarcityCounts['Common'] ?? 0;
        $commonCost = $commonCount * self::COMMON_FIXED;
        $nonCommonBudget = $poolTotal - $commonCost;

        // Calculate total points for non-Common
        $totalPoints = 0;
        foreach (self::MULTIPLIERS as $scarcity => $multiplier) {
            if ($scarcity === 'Common') continue;
            $totalPoints += ($scarcityCounts[$scarcity] ?? 0) * $multiplier;
        }

        if ($totalPoints === 0) {
            return array_fill_keys(array_keys(self::MULTIPLIERS), 0);
        }

        $perPoint = $nonCommonBudget / $totalPoints;

        $allocations = [];
        foreach (self::MULTIPLIERS as $scarcity => $multiplier) {
            if ($scarcity === 'Common') {
                $allocations[$scarcity] = (float) self::COMMON_FIXED;
            } else {
                $allocations[$scarcity] = round($perPoint * $multiplier, 6);
            }
        }

        return $allocations;
    }

    public function getMultipliers(): array
    {
        return self::MULTIPLIERS;
    }

    public function getCommonFixed(): float
    {
        return self::COMMON_FIXED;
    }
}
