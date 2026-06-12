<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class ReportDateRange
{
    public function __construct(
        public string $timezone,
        public CarbonImmutable $startLocal,
        public CarbonImmutable $endLocal,
        public CarbonImmutable $startUtc,
        public CarbonImmutable $endUtc,
    ) {}

    public static function fromOrganization(Organization $organization, string $startDate, string $endDate): self
    {
        $timezone = $organization->timezone ?: (string) config('app.timezone', 'UTC');

        $startLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$startDate} 00:00:00", $timezone)
            ->startOfDay();
        $endLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$endDate} 23:59:59", $timezone)
            ->endOfDay();

        return new self(
            timezone: $timezone,
            startLocal: $startLocal,
            endLocal: $endLocal,
            startUtc: $startLocal->utc(),
            endUtc: $endLocal->utc(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function periodKeys(string $groupBy): array
    {
        $cursor = match ($groupBy) {
            'weekly' => $this->startLocal->startOfWeek(CarbonInterface::MONDAY),
            'monthly' => $this->startLocal->startOfMonth(),
            default => $this->startLocal->startOfDay(),
        };

        $end = match ($groupBy) {
            'weekly' => $this->endLocal->startOfWeek(CarbonInterface::MONDAY),
            'monthly' => $this->endLocal->startOfMonth(),
            default => $this->endLocal->startOfDay(),
        };

        $keys = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->toDateString();

            $cursor = match ($groupBy) {
                'weekly' => $cursor->addWeek(),
                'monthly' => $cursor->addMonthNoOverflow(),
                default => $cursor->addDay(),
            };
        }

        return $keys;
    }
}
