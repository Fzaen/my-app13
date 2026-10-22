<?php

namespace App\Http\Requests;

use App\Models\ExportModel\ReportCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tglstart' => ['required', 'date_format:Y-m-d', 'before_or_equal:tglend'],
            'tglend' => ['required', 'date_format:Y-m-d', 'after_or_equal:tglstart'],
            'plu' => ['nullable', 'string', 'max:4000'],
            'member' => [
                'nullable',
                'required_if:laporan,exportmember-peritem,promo-detail-mypoint',
                'string',
                'max:4000',
            ],
            'supplier' => ['nullable', 'string', 'max:4000'],
            'divisi' => ['nullable', 'string', Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9'])],
            'departement' => ['nullable', 'string', 'regex:/^[A-Z0-9]{2}$/i'],
            'outlet' => ['nullable', 'string', Rule::in(['0', '1', '2', '3', '4', '5', '6', '7'])],
            'sub_outlet' => ['nullable', 'string', 'regex:/^[A-Z0-9]{2,3}$/i'],
            'larangan' => ['nullable', Rule::in(['y'])],
            'esse_djarum' => ['nullable', Rule::in(['y'])],
        ];
    }
}
