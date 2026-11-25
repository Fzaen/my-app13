<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlReport
{
    /**
     * @param  array{tglstart: string, tglend: string, tglend_exclusive: string}  $filters
     * @return Collection<int, \stdClass>
     */
    public static function pickerSummary(array $filters): Collection
    {
        $connection = DB::connection();
        $pickerExpression = "NULLIF(TRIM(detail.obi_picker), '')";

        return $connection->table('tbtr_obi_h as header')
            ->leftJoin('tbtr_obi_d as detail', function (JoinClause $join): void {
                $join->on('detail.obi_notrans', '=', 'header.obi_notrans')
                    ->whereRaw('detail.obi_tgltrans >= header.obi_tglpb::date')
                    ->whereRaw("detail.obi_tgltrans < header.obi_tglpb::date + INTERVAL '1 day'");
            })
            ->where('header.obi_maxdeliverytime', '>=', $filters['tglstart'])
            ->where('header.obi_maxdeliverytime', '<', $filters['tglend_exclusive'])
            ->whereNotNull('header.obi_recid')
            ->whereNotIn('header.obi_recid', ['1', '2'])
            ->whereRaw("header.obi_recid NOT LIKE 'B%'")
            ->selectRaw($pickerExpression.' AS obi_picker')
            ->selectRaw('COUNT(DISTINCT header.obi_nopb) AS jml_pb')
            ->selectRaw('COUNT(detail.obi_prdcd) AS jml_item')
            ->selectRaw('SUM(COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0)) AS rph_pb')
            ->selectRaw('COUNT(CASE WHEN detail.obi_qtyrealisasi <> 0 AND detail.obi_qtyrealisasi = detail.obi_qtyorder THEN 1 END) AS item_terpicking')
            ->selectRaw('COUNT(CASE WHEN detail.obi_qtyrealisasi > 0 AND detail.obi_qtyrealisasi < detail.obi_qtyorder THEN 1 END) AS item_sebagian')
            ->selectRaw('COUNT(CASE WHEN detail.obi_qtyrealisasi = 0 THEN 1 END) AS item_nol')
            ->selectRaw('SUM(CASE WHEN detail.obi_qtyrealisasi <> 0 AND detail.obi_qtyrealisasi = detail.obi_qtyorder THEN COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0) ELSE 0 END) AS rph_terpicking')
            ->selectRaw('SUM(CASE WHEN detail.obi_qtyrealisasi > 0 AND detail.obi_qtyrealisasi < detail.obi_qtyorder THEN COALESCE(detail.obi_qtyrealisasi * detail.obi_hargasatuan, 0) ELSE 0 END) AS rph_real_sebagian')
            ->selectRaw('SUM(CASE WHEN detail.obi_qtyrealisasi > 0 AND detail.obi_qtyrealisasi < detail.obi_qtyorder THEN COALESCE((detail.obi_qtyorder - detail.obi_qtyrealisasi) * detail.obi_hargasatuan, 0) ELSE 0 END) AS rph_lost_sebagian')
            ->selectRaw('SUM(CASE WHEN detail.obi_qtyrealisasi = 0 THEN COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0) ELSE 0 END) AS rph_nol')
            ->groupByRaw($pickerExpression)
            ->orderByRaw('obi_picker ASC NULLS FIRST')
            ->get();
    }

    /**
     * @param  array{tglstart: string, tglend: string, tglend_exclusive: string, picker: ?string, unassigned: bool}  $filters
     */
    public static function pickerDetails(array $filters): Builder
    {
        $query = DB::connection()->table('tbtr_obi_h as header')
            ->leftJoin('tbtr_obi_d as detail', function (JoinClause $join): void {
                $join->on('detail.obi_notrans', '=', 'header.obi_notrans')
                    ->whereRaw('detail.obi_tgltrans >= header.obi_tglpb::date')
                    ->whereRaw("detail.obi_tgltrans < header.obi_tglpb::date + INTERVAL '1 day'");
            })
            ->leftJoin('tbmaster_prodmast as product', function (JoinClause $join): void {
                $join->on(
                    'product.prd_prdcd',
                    '=',
                    DB::raw("LPAD(TRIM(detail.obi_prdcd::text), 6, '0') || '0'"),
                );
            })
            ->where('header.obi_maxdeliverytime', '>=', $filters['tglstart'])
            ->where('header.obi_maxdeliverytime', '<', $filters['tglend_exclusive'])
            ->whereNotNull('header.obi_recid')
            ->whereNotIn('header.obi_recid', ['1', '2'])
            ->whereRaw("header.obi_recid NOT LIKE 'B%'");

        if ($filters['unassigned']) {
            $query->where(function (Builder $query): void {
                $query->whereNull('detail.obi_picker')
                    ->orWhereRaw("TRIM(detail.obi_picker) = ''");
            });
        } else {
            $query->where('detail.obi_picker', $filters['picker']);
        }

        return $query
            ->selectRaw("COALESCE(NULLIF(TRIM(detail.obi_picker), ''), '- TANPA PICKER -') AS obi_picker")
            ->addSelect([
                'header.obi_nopb',
                'header.obi_notrans',
                'header.obi_kdmember',
                'header.obi_tglpb',
                'header.obi_maxdeliverytime',
                'product.prd_deskripsipanjang',
                'product.prd_kodedivisi AS div',
                'detail.obi_qtyorder',
                'detail.obi_qtyrealisasi',
            ])
            ->selectRaw("LPAD(TRIM(detail.obi_prdcd::text), 6, '0') AS plu")
            ->selectRaw(
                "CASE
                    WHEN header.obi_recid IS NULL THEN '*BELUM SEND*'
                    WHEN header.obi_recid = '1' THEN '*SEDANG PICKING'
                    WHEN header.obi_recid = '2' THEN '*SEDANG SCAN'
                    WHEN header.obi_recid = '3' THEN 'BELUM DRAFT STRUK'
                    WHEN header.obi_recid = '4' THEN 'SEDANG DRAFT STRUK'
                    WHEN header.obi_recid = '5' THEN 'SIAP STRUK'
                    WHEN header.obi_recid = '6' THEN 'SUDAH STRUK'
                    WHEN header.obi_recid = '7' THEN 'SET ONGKIR'
                    WHEN header.obi_recid LIKE 'B%' THEN 'BATAL'
                    ELSE ''
                END AS status_pb",
            )
            ->selectRaw(
                "CASE
                    WHEN detail.obi_qtyrealisasi < detail.obi_qtyorder AND detail.obi_qtyrealisasi > 0 THEN 'SEBAGIAN'
                    WHEN detail.obi_qtyrealisasi = 0 THEN 'NOL'
                    WHEN detail.obi_qtyrealisasi = detail.obi_qtyorder THEN 'TERPICKING'
                    ELSE 'CARI TAU'
                END AS ket_picking",
            )
            ->selectRaw('COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0) AS rph_order')
            ->selectRaw('COALESCE(detail.obi_qtyrealisasi * detail.obi_hargasatuan, 0) AS rph_realisasi')
            ->selectRaw('CASE WHEN detail.obi_qtyrealisasi <> 0 AND detail.obi_qtyrealisasi = detail.obi_qtyorder THEN COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0) ELSE 0 END AS rph_terpicking_100')
            ->selectRaw('CASE WHEN detail.obi_qtyrealisasi > 0 AND detail.obi_qtyrealisasi < detail.obi_qtyorder THEN COALESCE(detail.obi_qtyrealisasi * detail.obi_hargasatuan, 0) ELSE 0 END AS rph_real_sebagian')
            ->selectRaw('CASE WHEN detail.obi_qtyrealisasi > 0 AND detail.obi_qtyrealisasi < detail.obi_qtyorder THEN COALESCE((detail.obi_qtyorder - detail.obi_qtyrealisasi) * detail.obi_hargasatuan, 0) ELSE 0 END AS rph_lost_sebagian')
            ->selectRaw('CASE WHEN detail.obi_qtyrealisasi = 0 THEN COALESCE(detail.obi_qtyorder * detail.obi_hargasatuan, 0) ELSE 0 END AS rph_nol')
            ->selectRaw(
                "CASE
                    WHEN LEFT(product.prd_deskripsipanjang, 1) = '*' THEN 'NON AKTIF'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'N' THEN 'IGR_ONLY'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'N' THEN 'IGR_IDM'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'N' THEN 'IGR_OMI'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'Y' THEN 'IGR_IDM_OMI_OBI'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'N' THEN 'IGR_IDM_OMI'
                    WHEN product.prd_flagigr = 'Y' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'Y' THEN 'IGR_OMI_OBI'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'N' THEN 'IDM_ONLY'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'N' THEN 'IDM_OMI'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'Y' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'Y' THEN 'IDM_OMI_OBI'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'N' THEN 'OMI_ONLY'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'Y' AND product.prd_flagobi = 'Y' THEN 'OMI_OBI'
                    WHEN product.prd_flagigr = 'N' AND product.prd_flagidm = 'N' AND product.prd_flagomi = 'N' AND product.prd_flagobi = 'Y' THEN 'OBI_ONLY'
                    ELSE 'BELUM TAU'
                END AS flag_produk",
            )
            ->orderBy('header.obi_nopb')
            ->orderBy('header.obi_tglpb')
            ->orderBy('header.obi_notrans')
            ->orderBy('detail.obi_urut_pick')
            ->orderBy('detail.obi_urut_scan')
            ->orderBy('detail.obi_prdcd');
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function pickingStatusSummary(): Collection
    {
        $connection = DB::connection();
        $paymentDate = $connection->table('payment_klikigr as payment')
            ->selectRaw('MAX(payment.tgl_bayar)')
            ->whereColumn('payment.kode_member', 'header.obi_kdmember')
            ->whereColumn('payment.no_pb', 'header.obi_nopb')
            ->whereColumn('payment.no_trans', 'header.obi_notrans');

        $orders = $connection->table('tbtr_obi_h as header')
            ->select('header.obi_nopb')
            ->selectRaw(
                "CASE
                    WHEN header.obi_recid IS NULL THEN '*BELUM SEND*'
                    WHEN header.obi_recid = '1' THEN '*SEDANG PICKING'
                    WHEN header.obi_recid = '2' THEN '*SEDANG SCAN'
                    WHEN header.obi_recid = '3' THEN 'BELUM DRAFT STRUK'
                    WHEN header.obi_recid = '4' THEN 'SEDANG DRAFT STRUK'
                    WHEN header.obi_recid = '5' THEN 'SIAP STRUK'
                    WHEN header.obi_recid = '6' THEN 'SUDAH STRUK'
                    WHEN header.obi_recid LIKE 'B%' THEN 'BATAL - ' || header.obi_alasanbtl
                    ELSE ''
                END AS status",
            )
            ->addSelect('header.obi_maxsertim', 'header.obi_tglpb')
            ->selectRaw("COALESCE(header.obi_kdekspedisi ILIKE '%Ambil di Toko%', FALSE) AS is_pickup")
            ->selectRaw(
                "(header.obi_tipebayar = 'COD' OR header.obi_tipebayar IN ('i-Saku', 'VAMDR', 'VABRI', 'ISAKU', 'Virtual Account BCA', 'VABNI', 'VABCA', 'Virtual Account BNI')) AS is_cash_payment",
            )
            ->selectSub($paymentDate, 'payment_tgl_bayar')
            ->whereRaw("header.obi_tglpb >= date_trunc('year', CURRENT_DATE)")
            ->whereRaw("header.obi_tglpb < date_trunc('year', CURRENT_DATE) + INTERVAL '1 year'")
            ->where(function (Builder $query): void {
                $query->whereNull('header.obi_recid')
                    ->orWhereIn('header.obi_recid', ['1', '2', '3', '4', '5'])
                    ->orWhere(function (Builder $query): void {
                        $query->whereRaw('header.obi_sendpick >= CURRENT_DATE')
                            ->whereRaw("header.obi_sendpick < CURRENT_DATE + INTERVAL '1 day'")
                            ->where(function (Builder $query): void {
                                $query->where('header.obi_recid', '6')
                                    ->orWhereRaw("header.obi_recid LIKE 'B%'");
                            });
                    });
            });

        $ordersWithBucket = $connection->query()
            ->fromSub($orders, 'orders')
            ->select('orders.obi_nopb', 'orders.obi_tglpb', 'orders.status')
            ->selectRaw(
                "CASE
                    WHEN orders.status NOT LIKE 'BATAL%'
                        AND orders.obi_maxsertim::date < CURRENT_DATE
                    THEN CASE
                        WHEN orders.obi_maxsertim::time IN (TIME '14:00:00', TIME '15:00:00') AND NOT orders.is_pickup THEN 'y2'
                        WHEN orders.obi_maxsertim::time IN (TIME '18:00:00', TIME '19:00:00') AND NOT orders.is_pickup THEN 'y6'
                        WHEN orders.is_pickup THEN 'yat'
                    END
                    WHEN orders.status NOT LIKE 'BATAL%'
                        AND orders.obi_maxsertim::date = CURRENT_DATE
                    THEN CASE
                        WHEN orders.obi_maxsertim::time IN (TIME '14:00:00', TIME '15:00:00') AND NOT orders.is_pickup THEN 'td2'
                        WHEN orders.obi_maxsertim::time IN (TIME '18:00:00', TIME '19:00:00') AND NOT orders.is_pickup THEN 'td6'
                        WHEN orders.is_pickup THEN 'tdat'
                    END
                    WHEN orders.status NOT LIKE 'BATAL%'
                        AND orders.obi_maxsertim::date > CURRENT_DATE
                    THEN CASE
                        WHEN orders.obi_maxsertim::time IN (TIME '14:00:00', TIME '15:00:00') AND NOT orders.is_pickup THEN 'tm2'
                        WHEN orders.obi_maxsertim::time IN (TIME '18:00:00', TIME '19:00:00') AND NOT orders.is_pickup THEN 'tm6'
                        WHEN orders.is_pickup THEN 'tmat'
                    END
                    WHEN orders.status LIKE 'BATAL%'
                        AND orders.is_cash_payment
                        AND orders.payment_tgl_bayar < CURRENT_DATE + INTERVAL '1 day'
                    THEN 'batal'
                    ELSE 'manual'
                END AS bucket",
            )
            ->distinct();

        return $connection->query()
            ->fromSub($ordersWithBucket, 'orders')
            ->select('orders.status')
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'y2' THEN 1 END) AS y2")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'y6' THEN 1 END) AS y6")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'yat' THEN 1 END) AS yat")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'td2' THEN 1 END) AS td2")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'td6' THEN 1 END) AS td6")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'tdat' THEN 1 END) AS tdat")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'tm2' THEN 1 END) AS tm2")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'tm6' THEN 1 END) AS tm6")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'tmat' THEN 1 END) AS tmat")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'manual' THEN 1 END) AS cm")
            ->selectRaw("COUNT(CASE WHEN orders.bucket = 'batal' THEN 1 END) AS btl")
            ->groupBy('orders.status')
            ->orderByRaw(
                "CASE orders.status
                    WHEN '*BELUM SEND*' THEN 1
                    WHEN '*SEDANG PICKING' THEN 2
                    WHEN '*SEDANG SCAN' THEN 3
                    WHEN 'BELUM DRAFT STRUK' THEN 4
                    WHEN 'SEDANG DRAFT STRUK' THEN 5
                    WHEN 'SIAP STRUK' THEN 6
                    WHEN 'SUDAH STRUK' THEN 7
                    ELSE 8
                END",
            )
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function pickingSlSummary(): Collection
    {
        $connection = DB::connection();
        $itemTotals = $connection->table('tbtr_obi_d as detail')
            ->selectRaw('COUNT(detail.obi_prdcd) AS item')
            ->selectRaw(
                'SUM(ROUND(detail.obi_qtyorder * ((detail.obi_hargasatuan + detail.obi_ppn) - detail.obi_diskon), 2)) AS rph',
            )
            ->whereColumn('detail.obi_tgltrans', 'header.obi_tglpb')
            ->whereColumn('detail.obi_notrans', 'header.obi_notrans');

        $query = $connection->table('tbtr_obi_h as header')
            ->leftJoinLateral($itemTotals, 'line_totals')
            ->whereRaw("header.obi_maxsertim >= date_trunc('month', CURRENT_DATE)")
            ->whereRaw("header.obi_maxsertim < date_trunc('month', CURRENT_DATE) + INTERVAL '1 month'")
            ->whereRaw("COALESCE(header.obi_recid, 'N') NOT LIKE 'B%'")
            ->selectRaw('header.obi_maxsertim::date AS tanggal');

        foreach ([
            'jam14' => "header.obi_maxsertim::time IN (TIME '14:00:00', TIME '15:00:00')",
            'jam18' => "header.obi_maxsertim::time IN (TIME '18:00:00', TIME '19:00:00')",
        ] as $prefix => $timeCondition) {
            $query
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$timeCondition} THEN header.obi_nopb END) AS {$prefix}_jml_pb")
                ->selectRaw("SUM(CASE WHEN {$timeCondition} THEN COALESCE(line_totals.item, 0) ELSE 0 END) AS {$prefix}_jml_item")
                ->selectRaw("SUM(CASE WHEN {$timeCondition} THEN COALESCE(line_totals.rph, 0) ELSE 0 END) AS {$prefix}_rph")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$timeCondition} AND header.obi_draftstruk IS NOT NULL AND header.obi_tipebayar IS NOT NULL AND header.obi_recid > '3' THEN header.obi_nopb END) AS {$prefix}_sudah")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$timeCondition} AND header.obi_draftstruk IS NULL AND header.obi_tipebayar IS NOT NULL AND header.obi_recid = '3' THEN header.obi_nopb END) AS {$prefix}_belum")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$timeCondition} AND header.obi_maxsertim >= header.obi_draftstruk THEN header.obi_nopb END) AS {$prefix}_sesuai")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$timeCondition} AND header.obi_maxsertim < header.obi_draftstruk THEN header.obi_nopb END) AS {$prefix}_telat");
        }

        return $query
            ->groupByRaw('header.obi_maxsertim::date')
            ->orderByDesc('tanggal')
            ->get();
    }
}
