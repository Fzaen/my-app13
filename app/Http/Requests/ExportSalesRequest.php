<?php

namespace App\Http\Requests;

use App\Models\ExportModel\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExportSalesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'laporan' => ['required', Rule::in(ReportCatalog::types())],
            'tglstart' => $this->filterRules('tglstart', ['required', 'date_format:Y-m-d', 'before_or_equal:tglend']),
            'tglend' => $this->filterRules('tglend', ['required', 'date_format:Y-m-d', 'after_or_equal:tglstart']),
            'plu' => $this->filterRules('plu', ['nullable', 'string', 'max:4000']),
            'member' => [
                Rule::excludeIf(fn (): bool => ! $this->supportsReportFilter('member')),
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresReportFilter('member')),
                'string',
                'max:4000',
            ],
            'supplier' => $this->filterRules('supplier', ['nullable', 'string', 'max:4000']),
            'divisi' => $this->filterRules('divisi', [
                'nullable',
                'string',
                Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9']),
            ]),
            'departement' => $this->filterRules('departement', ['nullable', 'string', 'regex:/^[A-Z0-9]{2}$/i']),
            'outlet' => $this->filterRules('outlet', [
                'nullable',
                'string',
                Rule::in(['0', '1', '2', '3', '4', '5', '6', '7']),
            ]),
            'sub_outlet' => $this->filterRules('sub_outlet', ['nullable', 'string', 'regex:/^[A-Z0-9]{2,3}$/i']),
            'larangan' => $this->filterRules('larangan', ['nullable', Rule::in(['y'])]),
            'esse_djarum' => $this->filterRules('esse_djarum', ['nullable', Rule::in(['y'])]),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['laporan', 'tglstart', 'tglend'])) {
                    return;
                }

                $maxPeriodMonths = ReportCatalog::maxPeriodMonths(
                    $this->string('laporan')->toString(),
                );

                if ($maxPeriodMonths === null) {
                    return;
                }

                $startDate = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('tglstart')->toString());
                $endDate = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('tglend')->toString());

                if ($endDate->greaterThanOrEqualTo($startDate->addMonthsNoOverflow($maxPeriodMonths))) {
                    $validator->errors()->add(
                        'tglend',
                        "Rentang laporan ini maksimal {$maxPeriodMonths} bulan. Pilih tanggal yang lebih pendek.",
                    );
                }
            },
        ];
    }

    /**
     * @param  array<int, ValidationRule|string>  $rules
     * @return array<int, ValidationRule|string>
     */
    private function filterRules(string $filter, array $rules): array
    {
        return [
            Rule::excludeIf(fn (): bool => ! $this->supportsReportFilter($filter)),
            ...$rules,
        ];
    }

    private function supportsReportFilter(string $filter): bool
    {
        $report = $this->input('laporan');

        return is_string($report) && ReportCatalog::supportsFilter($report, $filter);
    }

    private function requiresReportFilter(string $filter): bool
    {
        $report = $this->input('laporan');

        return is_string($report) && in_array($filter, ReportCatalog::requiredFiltersFor($report), true);
    }
}
