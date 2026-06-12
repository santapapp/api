<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Models\Organization;
use App\Services\OrganizationContext;
use App\Support\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(OrganizationContext::class)->getRole() === 'owner';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('start_date'));
            $end = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('end_date'));

            if ($start === false || $end === false) {
                return;
            }

            if ($start->diffInDays($end) > 364) {
                $validator->errors()->add('end_date', 'Rentang laporan maksimal 365 hari.');
            }
        });
    }

    protected function failedAuthorization(): void
    {
        throw new AccessDeniedHttpException('Hanya owner organisasi yang dapat mengakses laporan.');
    }

    public function organization(): Organization
    {
        $organization = app(OrganizationContext::class)->get();

        if (! $organization) {
            abort(500, 'Konteks organisasi belum ditentukan.');
        }

        return $organization;
    }

    public function dateRange(): ReportDateRange
    {
        return ReportDateRange::fromOrganization(
            $this->organization(),
            (string) $this->validated('start_date'),
            (string) $this->validated('end_date'),
        );
    }
}
