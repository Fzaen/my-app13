<?php

namespace App\Exports;

use App\Models\ExportModel\PbDateReport;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use stdClass;
use UnexpectedValueException;

class PbDateReportExport extends TextColumnValueBinder implements FromQuery, WithCustomChunkSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly array $filters,
        private readonly string $connectionName,
        private readonly string $search = '',
    ) {}

    public function query(): Builder
    {
        return PbDateReport::query($this->filters, $this->connectionName, $this->search);
    }

    public function chunkSize(): int
    {
        return 25_000;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'PB',
            'TANGGAL',
            'NO',
            'KD MEMBER',
            'NAMA MEMBER',
            'KELURAHAN',
            'TGLSTRUK',
            'BAYAR',
            'PIN IPP',
            'PIN COD',
            'KET PIN',
            'TTL ORDER',
            'ITEM ORDER',
            'REAL ORDER',
            'REAL ITEM',
            'JRK EKSPEDISI',
            'FREE ONGKIR',
            'KENA ONGKIR',
            'JENIS',
            'JAM DSP',
            'LYTI',
            'REQ KIRIM',
            'KET',
        ];
    }

    /**
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        if (! $row instanceof stdClass) {
            throw new UnexpectedValueException('PB date report rows must be query result objects.');
        }

        $expeditionType = substr((string) $row->obi_kdekspedisi, 10, 5);
        $expeditionType = in_array($expeditionType, ['Mobil', 'Motor'], true)
            ? $expeditionType
            : 'SPI';
        $shippingCost = (float) ($row->obi_service_fee ?? 0) > 0
            ? $row->obi_service_fee
            : $row->obi_cod_pincode;
        $freeShipping = $row->ads_hadiah ?? $row->awi_cost ?? 0;
        $shippingCharged = $row->ads_hadiah !== null
            ? ($row->hdh_qty !== null ? '✓' : '✗')
            : $row->obi_ekspedisi;
        $status = ($row->ket ?? '').($row->ketdsp === 'ULANG' ? '*ULANG' : '');

        return [
            $row->pb,
            $row->tgl,
            $row->obi_notrans,
            $row->obi_kdmember,
            $row->cus_namamember,
            $row->cus_alamatmember8,
            $row->tglstruk,
            $row->obi_tipebayar,
            $row->pin,
            $shippingCost,
            $row->ket_pin,
            $row->ttl_order,
            $row->obi_itemorder,
            $row->ttl_real,
            $row->obi_realitem,
            $row->obi_jrkekspedisi,
            $freeShipping,
            $shippingCharged,
            $row->gttipe ?? $expeditionType,
            $row->jam_dsp,
            $row->loyalty,
            $row->req,
            $status,
        ];
    }
}
