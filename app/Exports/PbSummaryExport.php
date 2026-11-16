<?php

namespace App\Exports;

use App\Models\FrontEnd\ObiOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use stdClass;

class PbSummaryExport implements FromCollection, WithHeadings
{
    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'tgl',
        'pb',
        'sudahsend',
        'batal',
        'belum_sendhh',
        'sp_ambil',
        'sp_indopaket',
        'siap_packing',
        'siap_dsp',
        'siap_struk_spi',
        'siap_struk_ipp',
        'selesai_struk',
        'indo_paket',
        'ambil_ditoko',
    ];

    public function __construct(private readonly string $search = '') {}

    public function collection(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return ObiOrder::getDailySummaryForLastSevenDays()
            ->filter(function (stdClass $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $values = array_map(
                    static fn (mixed $value): string => (string) $value,
                    array_values(get_object_vars($row)),
                );

                return str_contains(mb_strtolower(implode(' ', $values)), $search);
            })
            ->map(fn (stdClass $row): array => array_map(
                fn (string $column): mixed => $row->{$column},
                self::COLUMNS,
            ))
            ->values();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'TGL',
            'ALL PB',
            'SDH_SEND',
            'BATAL',
            'BELUM_SEND',
            'PICKING AMBIL',
            'PICKING INDOPAKET',
            'SIAP PACKING',
            'SIAP DSP',
            'SIAP STRUK SPI',
            'SIAP STRUK IPP',
            'SELESAI STRUK',
            'INDO PAKET',
            'AMBIL DI SPI',
        ];
    }
}
