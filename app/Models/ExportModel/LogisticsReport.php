<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LogisticsReport extends Model
{
    protected $table = 'tbtr_mstran_d';

    public $timestamps = false;

    /**
     * @var array<string, array{file: string, headings: list<string>}>
     */
    private const REPORTS = [
        'log-bpb-all' => [
            'file' => 'log_bpb_all.php',
            'headings' => [
                'CAB', 'PERIODE', 'LOKASI_TOKO', 'LOKASI_DISPLAY', 'DIV', 'DEPT', 'KATB', 'PLUMCG', 'PLU',
                'MCG', 'DESK', 'FRAC', 'TAG', 'TAGIDM', 'TAGOMI', 'NOPO', 'PO_QTY', 'PO_RPH', 'PO_PPN',
                'PO_DISKON', 'NOBPB', 'BPB_QTY', 'BPP_RPH', 'BPB_PPN', 'BPB_DISKON', 'SLQTY', 'SLRPH',
                'TGLBPB', 'SUPPLIER', 'KODESUP', 'KET',
            ],
        ],
        'log-bpb-icc' => [
            'file' => 'log_bpb_mdih_from_icc.php',
            'headings' => ['PLU', 'DESK', 'FRAC', 'NOPO', 'NODOC', 'GROSS', 'QTYBPBICC'],
        ],
        'log-bpb-nodles' => [
            'file' => 'log_bpb_nodles.php',
            'headings' => [
                'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'UNIT', 'FRAC', 'KODESUP', 'MSTD_HRGSATUAN',
                'SUPPLIER', 'TGLPO', 'NOPO', 'PO_QTY', 'PO_CTN', 'PO_RPEXL', 'PO_RPINCL', 'TGLBPB',
                'NOBPB', 'BPB_QTY', 'BPB_CTN', 'BPP_RPEXL', 'BPB_RPINCL', 'KET',
            ],
        ],
        'log-bpb-picsupplier' => [
            'file' => 'log_bpb_picsupplier.php',
            'headings' => [
                'CAB', 'NAMACABANG', 'PERIODE', 'KODESUP', 'SUPPLIER', 'DSUP_NAMAVENDOR',
                'DSUP_PICVENDOR', 'DSUP_KONTAKVENDOR', 'DSUP_DRIVER_PICSUPPLIER',
                'DSUP_DRIVER_KONTAKSUPPLIER', 'DSUP_PICSALES', 'DSUP_KONTAKSALES',
                'DSUP_ATASAN_PICSALES', 'DSUP_ATASAN_KONTAKSALES', 'PLU', 'DESK', 'BPB_QTY', 'PO_RPH',
            ],
        ],
        'log-kesegaran' => [
            'file' => 'log_kesegaran.php',
            'headings' => [
                'PLU', 'DESK', 'FRC', 'FLAG', 'T_IGR', 'T_IDM', 'SELF_LIFE', 'SAT1', 'TURUN_PAJANG',
                'RETUR_SUPP', 'BATAS_RETUR_SUPP', 'UPDATE_TGL',
            ],
        ],
        'log-master-spi' => [
            'file' => 'log_master_spi.php',
            'headings' => [
                'KODEIGR', 'KODESPI', 'PLU', 'DESK', 'TAG', 'STATUS_RECID', 'CREATE_BY', 'CREATE_DT',
                'MODIFY_BY', 'MODIFY_DT', 'PLUWAJIB', 'STOCK',
            ],
        ],
        'log-pb-md' => [
            'file' => 'log_pb_md.php',
            'headings' => [
                'STATUS', 'DIV', 'DEPT', 'KATB', 'PLU', 'DESK', 'TAGIGR', 'TAGIDM', 'UNIT', 'FRAC',
                'FLAG', 'KODE', 'NASUP', 'NOPB', 'TGL_PB', 'QTY_PB', 'GROSS_PB', 'KETERANGANPB',
                'KET_PO', 'NO_PO', 'TGL_PO', 'QTY_PO', 'GROSS_PO', 'NO_BPB', 'TGL_BPB', 'QTY_BPB',
                'GROSS_BPB',
            ],
        ],
        'log-po-all-plu' => [
            'file' => 'log_po_all_plu.php',
            'headings' => [
                'KET', 'EXP_DATE', 'KET_PO', 'DIV', 'DEPT', 'KATB', 'PLUMCG', 'PLU', 'DSK', 'PRD_KODETAG',
                'PRD_FRAC', 'KD_SUP', 'NAMA_SUP', 'TPOD_NOPO', 'TGLPO', 'QTYPO', 'QTY_BPB', 'BPB_QTY',
                'SELESIH', 'TPOD_HRGSATUAN', 'TPOD_GROSS', 'BPB_GROSS', 'BPB_DISCRPH', 'BPB_MURNI',
                'BPB_PPN', 'MSTD_AVGCOST', 'TPOD_KODETAG', 'TPOD_JENISPB', 'TPOD_SATUANBELI',
                'TPOD_PERSENPPN', 'TPOD_RPHDISC1', 'TPOD_RPHDISC2', 'TPOD_RPHDISC2II', 'TPOD_RPHDISC2III',
                'TPOD_RPHDISC3', 'TPOD_RPHDISC4', 'TPOD_BONUSPO1', 'TPOD_BONUSPO2', 'TPOD_BONUSBPB1',
                'TPOD_BONUSBPB2', 'TPOD_RPHTTLDISC', 'TPOD_PPN', 'KETERANGANPB',
            ],
        ],
        'log-sl-supplier' => [
            'file' => 'log_sl_supplier.php',
            'headings' => [
                'CABANG_IGR', 'PERIODE', 'LOKASI_DISPLAY_DPD', 'DIV', 'DEPT', 'KAT', 'PLU', 'PLU_MCG',
                'DESK', 'FRAC', 'TAG', 'NOPO', 'QTYPO', 'TGLPO', 'RP_PO', 'NO_BPB', 'QTY_BPB', 'RP_BPB',
                'SL_QTY', 'SL_RP', 'TGL_BPB', 'NAMA_SUPPL', 'KD_SUPPL',
            ],
        ],
        'log-stock' => [
            'file' => 'log_stock.php',
            'headings' => [
                'DIV', 'DEPT', 'KATB', 'PLU', 'PLUMD', 'DESK', 'SPI', 'TAGIGR', 'TAGIDM', 'STATUS',
                'UNIT', 'FRAC', 'MINJUAL_MBT', 'LCOST_0', 'LCOST_1', 'LCOST_2', 'LCOST_3', 'ACOST_0',
                'ACOST_1', 'ACOST_2', 'ACOST_3', 'HRGJUAL_0', 'HRGJUAL_1', 'HRGJUAL_2', 'HRGJUAL_3',
                'STOCK_REAL', 'SALES', 'DSISPI', 'MARGIN_0', 'MARGIN_1', 'MARGIN_2', 'MARGIN_3', 'LT',
                'KF', 'PKM', 'PKMT', 'MPLUS', 'MPKM', 'MINDIS', 'MINOR', 'MAXPALET', 'POOUT', 'QTYBTB',
                'TGLBTB_TERAKHIR', 'FLAG', 'FLAG2', 'KOSUP', 'NASUP', 'KPH', 'QTY_AVGMM', 'QTY_AVGALL',
                'QTY_AVGIDM', 'QTY_AVGMM_PERHARIBELANJA', 'LOKASI_TOKO', 'LOKASI_GUDANG', 'MPLUSI',
                'PKM_KOEFISIEN', 'MAXPLANOTOKO', 'JENIS_ITEM', 'PRD_FLAGBKP1', 'PRD_FLAGBKP2',
                'PRD_TGLDISCONTINUE', 'JML_AVGMBRMB', 'JML_AVGMBRMM', 'HRG_OMI', 'QTY_AVGMB',
                'OBI_INTRANSIT', 'LEBAR', 'PANJANG', 'TINGGI', 'POANTRI', 'QTYPB_PROSES', 'STATUSPB',
                'QTYBPBICC', 'PBTRXOUT', 'STOCK_ALL', 'BTBPERTAMA', 'HARGABELICTN', 'DISCOUNT1',
                'DISCOUNT2', 'DISCOUNT2A', 'DISCOUNT2B', 'DISCOUNT3', 'DISCOUNT4', 'DISCOUNT5',
                'DISCOUNT6', 'CREATE_DT', 'TGLDAFTAR', 'TGLAKTIF',
            ],
        ],
        'log-tac' => [
            'file' => 'log_tac.php',
            'headings' => [
                'TGL', 'MSTD_PRDCD', 'DESK', 'PRD_FRAC', 'MSTD_AVGCOST', 'QTY', 'GROSS', 'KOSUP', 'NASUP',
            ],
        ],
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::REPORTS);
    }

    /** @return list<string> */
    public static function headings(string $report): array
    {
        return self::REPORTS[$report]['headings']
            ?? throw new InvalidArgumentException("Unsupported logistics report [{$report}].");
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function forExport(string $report, array $filters, ?string $connectionName = null): Builder
    {
        $definition = self::REPORTS[$report]
            ?? throw new InvalidArgumentException("Unsupported logistics report [{$report}].");
        $connection = DB::connection($connectionName);
        $ptglstart = '__EXPORT_TGLSTART__';
        $ptglend = '__EXPORT_TGLEND__';
        $plusales = '';
        $membersales = '';
        $viewsales_net = '';
        $supplier = '';
        $sql = '';

        require public_path('query_logistic_wedlama/'.$definition['file']);

        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
        $bindings = [];
        $sql = preg_replace_callback(
            "/'(__EXPORT_TGLSTART__|__EXPORT_TGLEND__)'/",
            static function (array $matches) use (&$bindings, $filters): string {
                $bindings[] = $matches[1] === '__EXPORT_TGLSTART__'
                    ? $filters['tglstart']
                    : $filters['tglend'];

                return '?';
            },
            $sql,
        ) ?? $sql;

        if ($report === 'log-bpb-icc') {
            $sql = str_replace(
                "MSTD_TGLPO::date >= '2026-04-15'",
                'MSTD_TGLPO::date BETWEEN ? AND ?',
                $sql,
            );
            $bindings = [$filters['tglstart'], $filters['tglend']];
        }

        if ($report === 'log-stock') {
            $sql = self::useRollingStockPeriods($sql);
        }

        $sql = rtrim(trim($sql), ';');
        $query = $connection->query()
            ->fromRaw('('.$sql.') AS logistics_report', $bindings)
            ->select(array_map('strtolower', $definition['headings']));

        $pluCodes = self::commaSeparatedValues($filters['plu'] ?? '');
        if ($pluCodes !== [] && in_array('plu', array_map('strtolower', $definition['headings']), true)) {
            $query->whereIn('plu', $pluCodes);
        }

        $memberCodes = self::commaSeparatedValues($filters['member'] ?? '');
        if ($memberCodes !== [] && $report === 'log-pb-md') {
            $query->whereIn('kode', $memberCodes);
        }

        $supplierColumn = match ($report) {
            'log-bpb-all', 'log-bpb-nodles', 'log-bpb-picsupplier' => 'kodesup',
            'log-po-all-plu' => 'kd_sup',
            'log-pb-md' => 'kode',
            'log-sl-supplier' => 'kd_suppl',
            'log-stock', 'log-tac' => 'kosup',
            default => null,
        };
        $supplierCodes = self::commaSeparatedValues($filters['supplier'] ?? '');

        if ($supplierCodes !== [] && $supplierColumn !== null) {
            $query->whereIn($supplierColumn, $supplierCodes);
        }

        return $query;
    }

    private static function useRollingStockPeriods(string $sql): string
    {
        $firstMonth = now()->startOfMonth()->subMonths(3);
        $months = [
            $firstMonth->format('m'),
            $firstMonth->copy()->addMonth()->format('m'),
            $firstMonth->copy()->addMonths(2)->format('m'),
        ];
        $monthExpression = static fn (string $column): string => implode(' + ', array_map(
            static fn (string $month): string => $column.'_'.$month,
            $months,
        ));

        foreach (['RSL_JMLMEMBER', 'RSL_QTY', 'RSL_RPH'] as $column) {
            $sql = preg_replace(
                '/'.preg_quote($column, '/').'_\d{2}\s*\+\s*'.preg_quote($column, '/').'_\d{2}\s*\+\s*'.preg_quote($column, '/').'_\d{2}/i',
                $monthExpression($column),
                $sql,
            ) ?? $sql;
        }

        $lastMonth = $firstMonth->copy()->addMonths(2);
        $sql = preg_replace(
            "/SLV_PERIODE\\s*=\\s*'\\d{6}'/i",
            "SLV_PERIODE = '".$lastMonth->format('Ym')."'",
            $sql,
        ) ?? $sql;

        return preg_replace(
            "/pid\\s*=\\s*'\\d{6}'/i",
            "pid = '".$lastMonth->format('mY')."'",
            $sql,
        ) ?? $sql;
    }

    /** @return list<string> */
    private static function commaSeparatedValues(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
