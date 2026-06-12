<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

final class ProductTrendReportRequest extends ReportDateRangeRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'product_id' => ['required', 'integer', 'min:1'],
        ]);
    }

    public function productId(): int
    {
        return (int) $this->validated('product_id');
    }
}
