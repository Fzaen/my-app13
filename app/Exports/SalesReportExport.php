<?php

namespace App\Exports;

use App\Models\ExportModel\LogisticsReport;
use App\Models\ExportModel\ReportCatalog;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use UnexpectedValueException;

class SalesReportExport extends TextColumnValueBinder implements FromQuery, WithCustomChunkSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(
        private readonly string $report,
        private readonly array $filters,
        private readonly string $connectionName,
    ) {}

    public function query(): Builder
    {
        return ReportCatalog::query($this->report, $this->filters, $this->connectionName);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return match ($this->report) {
            'sales-peritem' => [
                'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'MEMBER', 'KUNJUNGAN', 'STRUK',
                'QTY', 'LOKASI_TOKO', 'MAXDIS', 'MAXPLANO', 'GROSS', 'SALES', 'MARGIN', 'STOCK',
            ],
            'sales-peritem-member' => [
                'TGLPB', 'NOPB', 'TGLSTRUK', 'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'MEMBER',
                'NAMA', 'KUNJUNGAN', 'STRUK', 'QTY', 'GROSS', 'SALES', 'MARGIN', 'STOCK',
            ],
            'exportmember-peritem' => [
                'KODE', 'NAMA', 'PLU', 'DESKRIPSI', 'KASIR', 'KASSA', 'STRUK', 'QTY', 'RUPIAH',
                'MARGIN', 'TANGGALBELANJA',
            ],
            'sales-peritem-pertgl' => [
                'TGL', 'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'MEMBER', 'QTY', 'GROSS', 'SALES', 'MARGIN',
            ],
            'sales-peritem-perbulan' => [
                'BLN', 'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'MEMBER', 'QTY', 'GROSS', 'SALES', 'MARGIN',
            ],
            'sales-permember' => [
                'MEMBER', 'NAMA', 'ALAMAT', 'KELURAHAN', 'KOTA', 'KDPOS', 'HP', 'JARAK', 'TGLREGISTRASI',
                'OUTLET', 'SUB_OUTLET', 'NAMAOUTLET', 'NAMASUBOUTLET', 'KUNJUNGAN', 'STRUK', 'PRODUK',
                'DTL_GROSS', 'DTL_NETTO', 'DTL_MARGIN', 'ISK', 'QTY',
            ],
            'sales-permember-perbulan' => [
                'BULAN', 'MEMBER', 'NAMA', 'ALAMAT', 'KELURAHAN', 'KOTA', 'KDPOS', 'HP', 'JARAK',
                'TGLREGISTRASI', 'OUTLET', 'SUB_OUTLET', 'NAMAOUTLET', 'NAMASUBOUTLET', 'KUNJUNGAN',
                'PRODUK', 'DTL_GROSS', 'DTL_NETTO', 'DTL_MARGIN', 'ISK', 'QTY',
            ],
            'sales-awb' => [
                'OBI_NOPB', 'OBI_TGLPB', 'OBI_KDMEMBER', 'CUS_NAMAMEMBER', 'AWI_NOAWB', 'ONGKIR_CUSTOMER',
                'ONGKIR_POTONGAN', 'ONGKIR_TOTAL', 'DSP_NOLISTING', 'OBI_TGLSTRUK', 'OBI_TTLORDER',
                'OBI_TTLPPN', 'OBI_TTLDISKON', 'OBI_ITEMORDER', 'OBI_REALORDER', 'OBI_REALPPN',
                'OBI_REALDISKON', 'OBI_REALITEM', 'OBI_KDEKSPEDISI', 'OBI_JRKEKSPEDISI', 'OBI_USERAMBIL',
                'OBI_TGLAMBIL', 'OBI_TIPEBAYAR', 'OBI_TRXID', 'GROSS', 'SALES', 'MARGIN', 'QTY', 'DSP_RPH',
            ],
            'sales-perstruk' => [
                'TGLPB', 'NOPB', 'TGLSTRUK', 'TRJD_CUS_KODEMEMBER', 'NAMAMEMBER', 'NOSTRUK', 'PRODUK',
                'QTY_IN_PCS', 'GROSS', 'NETTO', 'MARGIN',
            ],
            'seles-armada-month-bytglpb-member' => [
                'OBI_TGLPB', 'EKSPEDISI', 'KODE_MEMBER', 'NAMA', 'MEMBER', 'JUMLAH_PB', 'GROSS', 'NETTO',
                'MARGIN', 'PRSNT', 'ONGKIR', 'MARGIN_AFS', 'PRSNT_MRGNT',
            ],
            'seles-armada-month-bytglpb' => [
                'OBI_TGLPB', 'EKSPEDISI', 'MEMBER', 'JUMLAH_PB', 'GROSS', 'NETTO', 'MARGIN', 'PRSNT',
                'ONGKIR', 'MARGIN_AFS', 'PRSNT_MRGNT',
            ],
            'sales-persupplier' => [
                'BULAN', 'KODESUPPLIER', 'NAMASUPPLIER', 'QTY', 'ITEM', 'GROSS', 'SALES', 'MARGIN',
            ],
            'sales-perdepartement' => [
                'BULAN', 'DIV', 'DEPT', 'NAMADEPARTEMENT', 'QTY', 'ITEM', 'GROSS', 'SALES', 'MARGIN',
            ],
            'pb-pertanggal' => [
                'OBI_TGLTRANS', 'PLU', 'DESK', 'QTY_ORDER', 'REALISASI', 'SEL_QTY', 'RPH_ORDER', 'RPH_REAL',
                'SEL_RPH',
            ],
            'pb-peritem' => [
                'OBI_RECID', 'KET', 'KET_ITEM', 'PB', 'OBI_KDMEMBER', 'CUS_NAMAMEMBER', 'TGLSTRUK',
                'OBI_TGLTRANS', 'OBI_NOTRANS', 'TRJD_PRDCD', 'PRD_DESKRIPSIPANJANG', 'HRGSATUAN',
                'OBI_QTYORDER', 'OBI_QTYREALISASI', 'POBI_QTY', 'POBI_CREATE_BY', 'OBI_URUT_PICK', 'JAM_PICK',
                'JAM_SCAN', 'OBI_PICKER', 'OBI_HARGAWEB', 'OBI_QTY_HITUNGULANG', 'OBI_QTYBA',
                'POBI_NOCONTAINER', 'SELISIH',
            ],
            'pb-picking-pertanggal' => [
                'KET', 'PB', 'TGLPB', 'NOTRANS', 'TGLSTRUK', 'MEMBER', 'NAMA', 'QTYORDER', 'REALISASI',
                'QTYBA_RK', 'PICKING', 'PICKER', 'SCAN', 'SCANNING',
            ],
            'pb-kirim-pertanggal' => ['TGL', 'SEBELUMNYA', 'HARI_INI', 'AMBIL_SPI', 'KIRIM'],
            'pb-jam-masuk' => ['BULAN', 'JAM', 'TOTAL'],
            'pb-bark' => [
                'KET', 'PB', 'TGLPB', 'NOTRANS', 'TGLSTRUK', 'MEMBER', 'NAMA', 'QTYORDER', 'REALISASI',
                'QTYBA_RK', 'PICKING', 'PICKER', 'SCAN', 'SCANNING',
            ],
            'pb-sertim' => [
                'PB', 'TGL', 'NOTRANS', 'OBI_COD_PINCODE', 'CUS_NAMAMEMBER', 'JAM_DSP', 'OBI_NOSJ',
                'OBI_TGLCETAKSJ', 'OBI_TGLSERTIM_LOGISTIK', 'KET_PIN', 'STI_PIN', 'STI_RECEIVERNAME',
            ],
            'promo-cb-aktif', 'promo-cb-periode' => [
                'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'CASHBACK', 'MINJUAL', 'MINPRODUK', 'MAXJU', 'MAXRPH',
                'REDEEMPOINT', 'ALOKASI', 'ALOKASIKELUAR', 'SISALOKASI', 'PLATINUM', 'MERAH', 'BIRU',
                'TGLAWAL', 'TGLAKHIR', 'KODE', 'CBH_NAMAPROMOSI', 'LOKASI',
            ],
            'promo-detail-mypoint' => [
                'KET', 'TOKO', 'MEMBER', 'TRX', 'POIN', 'CREATE_DT', 'MODIFY_DT', 'REDEEMPOIN', 'NO_HP',
                'STATUS',
            ],
            'promo-gift-aktif', 'promo-gift-periode' => [
                'PERIODE', 'AWAL', 'AKHIR', 'KODE', 'NAMAPROMO', 'DIV', 'DEPT', 'KATB', 'PLU', 'DESK',
                'PCS', 'RPH', 'MINSPONSOR', 'PLUHDH', 'HDH', 'ALOKASI', 'ALOKASIKELUAR', 'SISAALOKASI',
            ],
            'promo-gift-allho' => [
                'KDPROMO', 'NAMA', 'MIN_BELANJA', 'MIN_SPONSOR', 'MAXPERHARI', 'MAXFREK_PERHARI', 'PLU',
                'DESK', 'FRAC', 'JML_HADIAH', 'AWAL', 'AKHIR', 'JUMLAH_ALOKASI', 'ALOKASI_KELUAR',
                'ACOSTPCS', 'SISA_ALOKASI', 'GFH_KODEPERJANJIAN', 'STOCK',
            ],
            'promo-item-larangan' => [
                'PLU', 'DESK', 'NON_RECORDID', 'PRD_KODEDIVISI', 'DEPT', 'KATB', 'DEP_NAMADEPARTEMENT',
                'KAT_NAMAKATEGORI', 'NON_CREATE_BY', 'NON_CREATE_DT', 'NON_MODIFY_BY', 'NON_MODIFY_DT',
                'PRD_KODEIGR',
            ],
            'promo-mypoint' => [
                'TRJD_CUS_KODEMEMBER', 'NAMA', 'IGR_PEROLEHAN', 'SPI_PEROLEHAN', 'IGR_PEROLEHAN_GAGAL',
                'SPI_PEROLEHAN_GAGAL', 'IGR_PENUKARAN', 'IGR_REDEEM', 'SPI_PENUKARAN', 'SPI_REDEEM', 'SISA',
            ],
            'promo-pembatasan' => [
                'PLU', 'DESK', 'FRAC', 'STOCK_PCS', 'STOCK_CTN', 'PENGINPUT', 'BIRU', 'BIRUPLUS', 'FREEPASS',
                'RETAILERMERAH', 'SILVER', 'GOLD1', 'GOLD2', 'GOLD3', 'PLATINUM',
            ],
            'promo-tidak-ada-promo' => ['PLU', 'DESK', 'STOCK', 'PROMO_2024', 'PROMO_2025'],
            'log-bpb-all',
            'log-bpb-icc',
            'log-bpb-nodles',
            'log-bpb-picsupplier',
            'log-kesegaran',
            'log-master-spi',
            'log-pb-md',
            'log-po-all-plu',
            'log-sl-supplier',
            'log-stock',
            'log-tac' => LogisticsReport::headings($this->report),
            default => throw new UnexpectedValueException("Unsupported export report [{$this->report}]."),
        };
    }

    /**
     * @param  \stdClass  $row
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        if (! $row instanceof \stdClass) {
            throw new UnexpectedValueException('Sales report rows must be query result objects.');
        }

        return array_values(get_object_vars($row));
    }
}
