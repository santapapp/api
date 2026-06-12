<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

final class ProductBestsellersReportRequest extends ReportDateRangeRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 10);
    }
}
