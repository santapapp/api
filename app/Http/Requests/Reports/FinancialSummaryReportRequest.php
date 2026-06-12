<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Validation\Rule;

final class FinancialSummaryReportRequest extends ReportDateRangeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
        ]);
    }

    public function groupBy(): string
    {
        return (string) ($this->validated('group_by') ?? 'daily');
    }
}
