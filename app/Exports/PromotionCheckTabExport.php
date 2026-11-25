<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use stdClass;
use UnexpectedValueException;

class PromotionCheckTabExport extends TextColumnValueBinder implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, array{fields: list<string>, headings: list<string>}>
     */
    private const COLUMNS = [
        'descriptions' => [
            'fields' => [
                'promotion_type',
                'promo_code',
                'promo_name',
                'start_date',
                'end_date',
                'mechanism',
                'value',
                'minimum',
                'created_by',
            ],
            'headings' => [
                'Jenis',
                'Kode',
                'Nama Promo',
                'Mulai',
                'Selesai',
                'Mekanisme',
                'Nilai / Hadiah',
                'Minimum',
                'Dibuat Oleh',
            ],
        ],
        'recipients' => [
            'fields' => [
                'promotion_type',
                'member_code',
                'member_name',
                'promo_code',
                'receipt_number',
                'purchase_number',
                'transaction_date',
                'reward',
                'reward_value',
                'created_by',
            ],
            'headings' => [
                'Jenis',
                'Member',
                'Nama',
                'Kode Promo',
                'No. Transaksi',
                'No. PB',
                'Tanggal',
                'Hadiah',
                'Nilai',
                'Kasir',
            ],
        ],
        'registered' => [
            'fields' => [
                'promotion_type',
                'status',
                'member_code',
                'member_name',
                'promo_code',
            ],
            'headings' => [
                'Jenis',
                'Status',
                'Member',
                'Nama',
                'Kode Promo',
            ],
        ],
        'details' => [
            'fields' => [
                'promotion_type',
                'plu',
                'description',
                'value',
                'multiplier',
                'minimum',
                'maximum',
                'maximum_per_member',
                'allocation',
                'promo_code',
            ],
            'headings' => [
                'Jenis',
                'PLU',
                'Deskripsi',
                'Nilai',
                'Kelipatan',
                'Minimum',
                'Maksimum',
                'Maks. Member / Hari',
                'Alokasi',
                'Kode Promo',
            ],
        ],
    ];

    public function __construct(
        private readonly Collection $rows,
        private readonly string $tab,
    ) {
        if (! array_key_exists($this->tab, self::COLUMNS)) {
            throw new \InvalidArgumentException("Unsupported promotion tab [{$this->tab}].");
        }
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return self::COLUMNS[$this->tab]['headings'];
    }

    /** @return list<mixed> */
    public function map(mixed $row): array
    {
        if (! $row instanceof stdClass) {
            throw new UnexpectedValueException('Promotion export rows must be query result objects.');
        }

        return array_map(
            static fn (string $field): mixed => $row->{$field},
            self::COLUMNS[$this->tab]['fields'],
        );
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (
            $cell->getRow() > 1
            && is_string($value)
            && ! is_numeric($value)
            && preg_match('/^\s*[=+\-@]/u', $value) === 1
        ) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
