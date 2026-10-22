<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderReport extends Model
{
    protected $table = 'tbtr_obi_h';

    public $timestamps = false;

    /**
     * @param  array<string, string>  $filters
     */
    public static function forExport(string $report, array $filters, ?string $connectionName = null): Builder
    {
        return match ($report) {
            'pb-pertanggal' => self::salesByTransactionDate($filters, $connectionName),
            'pb-peritem' => self::detailsByItem($filters, $connectionName),
            'pb-picking-pertanggal' => self::pickingByDate($filters, false, $connectionName),
            'pb-kirim-pertanggal' => self::shippingByDate($filters, $connectionName),
            'pb-jam-masuk' => self::ordersByEntryTime($filters, $connectionName),
            'pb-bark' => self::pickingByDate($filters, true, $connectionName),
            'pb-sertim' => self::handoverStatus($filters, $connectionName),
            default => throw new InvalidArgumentException("Unsupported PB report [{$report}]."),
        };
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesByTransactionDate(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $pluExpression = "SUBSTRING(detail.obi_prdcd, 1, 6) || '0'";

        return $connection->table('tbtr_obi_d as detail')
            ->join('tbtr_obi_h as order_header', function (JoinClause $join): void {
                $join->whereRaw('detail.obi_tgltrans::date = order_header.obi_tgltrans::date')
                    ->on('detail.obi_notrans', '=', 'order_header.obi_notrans')
                    ->where('order_header.obi_recid', 'not like', 'B%');
            })
            ->leftJoin('tbmaster_prodmast as product', 'detail.obi_prdcd', '=', 'product.prd_prdcd')
            ->whereBetween(DB::raw('detail.obi_tgltrans::date'), [$filters['tglstart'], $filters['tglend']])
            ->selectRaw('detail.obi_tgltrans AS obi_tgltrans')
            ->selectRaw($pluExpression.' AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('SUM(detail.obi_qtyorder) AS qty_order')
            ->selectRaw('SUM(detail.obi_qtyrealisasi) AS realisasi')
            ->selectRaw('SUM(detail.obi_qtyrealisasi) - SUM(detail.obi_qtyorder) AS sel_qty')
            ->selectRaw('SUM(detail.obi_qtyorder * detail.obi_hargasatuan) AS rph_order')
            ->selectRaw('SUM(detail.obi_qtyrealisasi * detail.obi_hargasatuan) AS rph_real')
            ->selectRaw('(SUM(detail.obi_qtyrealisasi * detail.obi_hargasatuan) - SUM(detail.obi_qtyorder * detail.obi_hargasatuan)) AS sel_rph')
            ->groupBy('detail.obi_tgltrans')
            ->groupByRaw($pluExpression)
            ->groupBy('product.prd_deskripsipanjang')
            ->orderBy('detail.obi_tgltrans')
            ->orderBy('plu');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function detailsByItem(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $query = $connection->table('tbtr_obi_d as detail')
            ->leftJoin('tbtr_obi_h as order_header', function (JoinClause $join): void {
                $join->whereRaw('detail.obi_tgltrans::date = order_header.obi_tgltrans::date')
                    ->on('detail.obi_notrans', '=', 'order_header.obi_notrans');
            })
            ->leftJoin('tbmaster_customer as customer', function (JoinClause $join): void {
                $join->on('order_header.obi_kdmember', '=', 'customer.cus_kodemember')
                    ->where('customer.cus_kodeigr', '1M');
            })
            ->leftJoin('tbmaster_prodmast as product', 'detail.obi_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbtr_packing_obi as packing', function (JoinClause $join): void {
                $join->whereRaw('detail.obi_tgltrans::date = packing.pobi_tgltransaksi::date')
                    ->on('detail.obi_notrans', '=', 'packing.pobi_notransaksi')
                    ->on('detail.obi_prdcd', '=', 'packing.pobi_prdcd');
            })
            ->whereBetween(DB::raw('order_header.obi_tglpb::date'), [$filters['tglstart'], $filters['tglend']])
            ->whereBetween(DB::raw('detail.obi_tgltrans::date'), [$filters['tglstart'], $filters['tglend']]);

        self::applyPluFilter($query, $filters, 'detail.obi_prdcd');

        return $query
            ->selectRaw('order_header.obi_recid AS obi_recid')
            ->selectRaw(self::statusExpression('order_header.obi_recid').' AS ket')
            ->selectRaw("CASE WHEN order_header.obi_recid IS NULL THEN 'PICKING' WHEN order_header.obi_recid = '2' THEN 'BATAL' END AS ket_item")
            ->selectRaw("RTRIM(SUBSTRING(order_header.obi_nopb, 1, 7), '/') AS pb")
            ->selectRaw('order_header.obi_kdmember AS obi_kdmember')
            ->selectRaw('customer.cus_namamember AS cus_namamember')
            ->selectRaw("TO_CHAR(order_header.obi_tglstruk, 'DD-MON-YY') AS tglstruk")
            ->selectRaw("TO_CHAR(detail.obi_tgltrans, 'DD-MON-YY') AS obi_tgltrans")
            ->selectRaw('detail.obi_notrans AS obi_notrans')
            ->selectRaw('detail.obi_prdcd AS trjd_prdcd')
            ->selectRaw('product.prd_deskripsipanjang AS prd_deskripsipanjang')
            ->selectRaw('(detail.obi_hargasatuan + detail.obi_ppn) AS hrgsatuan')
            ->selectRaw('detail.obi_qtyorder AS obi_qtyorder')
            ->selectRaw('detail.obi_qtyrealisasi AS obi_qtyrealisasi')
            ->selectRaw('packing.pobi_qty AS pobi_qty')
            ->selectRaw('packing.pobi_create_by AS pobi_create_by')
            ->selectRaw('detail.obi_urut_pick AS obi_urut_pick')
            ->selectRaw('detail.obi_pick_dt AS jam_pick')
            ->selectRaw('detail.obi_scan_dt AS jam_scan')
            ->selectRaw('detail.obi_picker AS obi_picker')
            ->selectRaw('detail.obi_hargaweb AS obi_hargaweb')
            ->selectRaw('detail.obi_qty_hitungulang AS obi_qty_hitungulang')
            ->selectRaw('detail.obi_qtyba AS obi_qtyba')
            ->selectRaw('packing.pobi_nocontainer AS pobi_nocontainer')
            ->selectRaw('(detail.obi_qtyorder - packing.pobi_qty) AS selisih')
            ->orderBy('pb')
            ->orderBy('detail.obi_tgltrans')
            ->orderBy('detail.obi_notrans');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function pickingByDate(array $filters, bool $backordersOnly, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $query = $connection->table('tbtr_obi_d as detail')
            ->leftJoin('tbtr_obi_h as order_header', function (JoinClause $join) use ($filters): void {
                $join->whereRaw('detail.obi_tgltrans::date = order_header.obi_tgltrans::date')
                    ->on('detail.obi_notrans', '=', 'order_header.obi_notrans')
                    ->whereRaw('order_header.obi_tglpb::date BETWEEN ? AND ?', [
                        $filters['tglstart'],
                        $filters['tglend'],
                    ]);
            })
            ->leftJoin('tbmaster_customer as customer', function (JoinClause $join): void {
                $join->on('order_header.obi_kdmember', '=', 'customer.cus_kodemember')
                    ->where('customer.cus_kodeigr', '1M');
            })
            ->leftJoin('tbtr_packing_obi as packing', function (JoinClause $join): void {
                $join->whereRaw('detail.obi_tgltrans::date = packing.pobi_tgltransaksi::date')
                    ->on('detail.obi_notrans', '=', 'packing.pobi_notransaksi')
                    ->on('detail.obi_prdcd', '=', 'packing.pobi_prdcd');
            })
            ->leftJoin('tbmaster_user as picker_user', 'detail.obi_picker', '=', 'picker_user.userid')
            ->leftJoin('tbmaster_user as scanner_user', 'packing.pobi_create_by', '=', 'scanner_user.userid')
            ->whereBetween(DB::raw('detail.obi_tgltrans::date'), [$filters['tglstart'], $filters['tglend']]);

        self::applyPluFilter($query, $filters, 'detail.obi_prdcd');

        if ($backordersOnly) {
            $query->whereRaw('COALESCE(detail.obi_qtyba, 0) > 0');
        }

        $query->selectRaw(self::statusExpression('order_header.obi_recid').' AS ket')
            ->selectRaw("RTRIM(SUBSTRING(order_header.obi_nopb, 1, 7), '/') AS pb")
            ->selectRaw('order_header.obi_tglpb AS tglpb')
            ->selectRaw('detail.obi_notrans AS notrans')
            ->selectRaw('order_header.obi_tglstruk AS tglstruk')
            ->selectRaw('order_header.obi_kdmember AS member')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('detail.obi_qtyorder AS qtyorder')
            ->selectRaw('detail.obi_qtyrealisasi AS realisasi')
            ->selectRaw('detail.obi_qtyba AS qtyba_rk')
            ->selectRaw('detail.obi_picker AS picking')
            ->selectRaw('picker_user.username AS picker')
            ->selectRaw('packing.pobi_create_by AS scan')
            ->selectRaw('scanner_user.username AS scanning');

        return $query
            ->orderBy('pb')
            ->orderBy('detail.obi_tgltrans')
            ->orderBy('detail.obi_notrans')
            ->orderBy('detail.obi_prdcd');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function shippingByDate(array $filters, ?string $connectionName): Builder
    {
        $sql = <<<'SQL'
            WITH periode AS (
                SELECT ?::date AS tgl_awal,
                       (?::date + INTERVAL '1 month')::date AS tgl_akhir
            ),
            daftar_tanggal AS (
                SELECT generate_series(tgl_awal, tgl_akhir, '1 day'::interval)::date AS tgl
                FROM periode
            ),
            data_utama AS (
                SELECT h.obi_tgltrans::date AS tgl,
                       SUM(CASE WHEN s.dsp_tglpb::date = s.dsp_create_dt::date
                                      AND h.obi_kdekspedisi LIKE '%Indopaket%' THEN 1 ELSE 0 END) AS hari_ini,
                       SUM(CASE WHEN h.obi_kdekspedisi LIKE '%Ambil di%'
                                      AND s.dsp_tglpb::date = s.dsp_create_dt::date THEN 1 ELSE 0 END) AS ambil_spi
                FROM tbtr_obi_h h
                CROSS JOIN periode p
                LEFT JOIN tbtr_dsp_spi s ON s.dsp_nopb = h.obi_nopb
                WHERE h.obi_tgltrans::date BETWEEN p.tgl_awal AND p.tgl_akhir
                GROUP BY h.obi_tgltrans::date
            ),
            data_kirim AS (
                SELECT h.obi_tglstruk::date AS tgl,
                       COUNT(h.obi_nopb) AS kirim
                FROM tbtr_obi_h h
                CROSS JOIN periode p
                WHERE h.obi_tglstruk::date BETWEEN p.tgl_awal AND p.tgl_akhir
                  AND h.obi_kdekspedisi LIKE '%Indopaket%'
                GROUP BY h.obi_tglstruk::date
            ),
            data_sebelumnya AS (
                SELECT dt.tgl,
                       COUNT(h.obi_nopb) AS sebelumnya
                FROM daftar_tanggal dt
                LEFT JOIN tbtr_obi_h h
                    ON h.obi_kdekspedisi LIKE '%Indopaket%'
                   AND h.obi_tgltrans::date < dt.tgl
                   AND h.obi_tglstruk::date >= dt.tgl
                LEFT JOIN tbtr_dsp_spi s
                    ON s.dsp_nopb = h.obi_nopb
                   AND s.dsp_create_dt::date <= CASE
                       WHEN dt.tgl = (SELECT tgl_akhir FROM periode)
                       THEN dt.tgl + INTERVAL '1 day'
                       ELSE dt.tgl
                   END
                GROUP BY dt.tgl
            )
            SELECT TO_CHAR(dt.tgl, 'DD-MON-YY') AS tgl,
                   COALESCE(ds.sebelumnya, 0) AS sebelumnya,
                   COALESCE(du.hari_ini, 0) AS hari_ini,
                   COALESCE(du.ambil_spi, 0) AS ambil_spi,
                   COALESCE(dk.kirim, 0) AS kirim
            FROM daftar_tanggal dt
            LEFT JOIN data_utama du ON du.tgl = dt.tgl
            LEFT JOIN data_kirim dk ON dk.tgl = dt.tgl
            LEFT JOIN data_sebelumnya ds ON ds.tgl = dt.tgl
            ORDER BY dt.tgl
            SQL;

        return DB::connection($connectionName)->query()
            ->fromRaw("({$sql}) AS pb_shipping", [$filters['tglstart'], $filters['tglend']])
            ->select([
                'pb_shipping.tgl',
                'pb_shipping.sebelumnya',
                'pb_shipping.hari_ini',
                'pb_shipping.ambil_spi',
                'pb_shipping.kirim',
            ])
            ->orderBy('pb_shipping.tgl');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function ordersByEntryTime(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $bucket = 'FLOOR(EXTRACT(HOUR FROM order_header.obi_createdt) / 2)::int';
        $hourLabel = "('Jam ' || ({$bucket} * 2)::text || '-' || (({$bucket} + 1) * 2)::text)";

        return $connection->table('tbtr_obi_h as order_header')
            ->whereBetween(DB::raw('order_header.obi_createdt::date'), [$filters['tglstart'], $filters['tglend']])
            ->where('order_header.obi_recid', 'not like', '%B%')
            ->selectRaw("TO_CHAR(order_header.obi_tglpb, 'MM-YYYY') AS bulan")
            ->selectRaw($hourLabel.' AS jam')
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw("TO_CHAR(order_header.obi_tglpb, 'MM-YYYY')")
            ->groupByRaw($bucket)
            ->orderBy('bulan')
            ->orderByRaw($bucket);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function handoverStatus(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $pickupMembers = $connection->table('tbtr_obi_h')
            ->select('obi_kdmember')
            ->where('obi_kdekspedisi', 'Ambil di Stock Point Indogrosir')
            ->where('obi_recid', '6')
            ->groupBy('obi_kdmember');
        $dsp = $connection->table('tbtr_dsp_spi')
            ->select('dsp_nopb', 'dsp_tglpb')
            ->selectRaw("TO_CHAR(dsp_create_dt, 'DD-MON-YY HH24:MI:SS') AS jam_dsp")
            ->whereBetween(DB::raw('dsp_tglpb::date'), [$filters['tglstart'], $filters['tglend']]);
        $pinsB = $connection->table('tbtr_serahterima_ipp')
            ->select('sti_noorder')
            ->selectRaw('sti_pin AS pin_b')
            ->where('sti_pin', 'like', 'B%');
        $pinsA = $connection->table('tbtr_serahterima_ipp')
            ->selectRaw("RTRIM(SUBSTRING(sti_noorder, 2, 8), '/') AS order_c")
            ->selectRaw('sti_pin AS pin_c')
            ->where('sti_noorder', 'like', 'A%')
            ->where('sti_pin', 'like', 'A%');
        $pinsSecondB = $connection->table('tbtr_serahterima_ipp')
            ->selectRaw("RTRIM(SUBSTRING(sti_noorder, 2, 8), '/') AS order_d")
            ->selectRaw('sti_pin AS pin_d')
            ->where('sti_noorder', 'like', 'A%')
            ->where('sti_pin', 'like', 'B%');
        $orderNumber = "RTRIM(SUBSTRING(order_header.obi_nopb, 1, 7), '/')";
        $status = "CASE
            WHEN pins_second_b.pin_d IS NOT NULL THEN 'SUDAH pin B 2x'
            WHEN pins_a.pin_c IS NOT NULL THEN 'SUDAH INPUT pin RECREATE'
            WHEN pins_b.pin_b IS NOT NULL THEN 'SUDAH pin B 1x'
            WHEN handover.sti_pin IS NOT NULL AND handover.sti_receivername = '' THEN 'MASIH TMS'
            WHEN handover.sti_pin IS NOT NULL AND handover.sti_receivername <> '' THEN 'SUDAH INPUT pin'
            WHEN order_header.obi_kdekspedisi LIKE '%Ambil%' AND pickup_members.obi_kdmember IS NULL THEN 'BELUM PERNAH AMBIL DI SPI'
            WHEN order_header.obi_kdekspedisi LIKE '%Ambil%' THEN 'AMBIL DI SPI'
            ELSE 'BELUM INPUT pin'
        END";

        return $connection->table('tbtr_obi_h as order_header')
            ->leftJoin('tbmaster_customer as customer', function (JoinClause $join): void {
                $join->on('order_header.obi_kdmember', '=', 'customer.cus_kodemember')
                    ->where('customer.cus_kodeigr', '1M');
            })
            ->leftJoin('tbtr_awb_ipp as awb', function (JoinClause $join) use ($orderNumber): void {
                $join->whereRaw("{$orderNumber} = awb.awi_noorder");
            })
            ->leftJoin('tbtr_serahterima_ipp as handover', function (JoinClause $join) use ($orderNumber): void {
                $join->whereRaw('UPPER(SUBSTRING(awb.awi_pincode, 1, 1)) = UPPER(SUBSTRING(handover.sti_pin, 1, 1))')
                    ->whereRaw("handover.sti_noorder = {$orderNumber}");
            })
            ->leftJoinSub($pickupMembers, 'pickup_members', 'order_header.obi_kdmember', '=', 'pickup_members.obi_kdmember')
            ->leftJoinSub($pinsB, 'pins_b', 'pins_b.sti_noorder', '=', DB::raw($orderNumber))
            ->leftJoinSub($pinsA, 'pins_a', 'pins_a.order_c', '=', DB::raw($orderNumber))
            ->leftJoinSub($pinsSecondB, 'pins_second_b', 'pins_second_b.order_d', '=', DB::raw($orderNumber))
            ->leftJoinSub($dsp, 'dsp', function (JoinClause $join) use ($orderNumber): void {
                $join->whereRaw("RTRIM(SUBSTRING(dsp.dsp_nopb, 1, 7), '/') = {$orderNumber}")
                    ->whereRaw('order_header.obi_tglpb::date = dsp.dsp_tglpb::date');
            })
            ->whereBetween(DB::raw('order_header.obi_tglpb::date'), [$filters['tglstart'], $filters['tglend']])
            ->where('order_header.obi_recid', '5')
            ->where('order_header.obi_kdekspedisi', 'like', 'Indopaket%')
            ->selectRaw("RTRIM(SUBSTRING(order_header.obi_nopb, 1, 7), '/') AS pb")
            ->selectRaw('order_header.obi_createdt AS tgl')
            ->selectRaw('order_header.obi_notrans AS notrans')
            ->selectRaw('order_header.obi_cod_pincode AS obi_cod_pincode')
            ->selectRaw('customer.cus_namamember AS cus_namamember')
            ->selectRaw('dsp.jam_dsp AS jam_dsp')
            ->selectRaw('order_header.obi_nosj AS obi_nosj')
            ->selectRaw('CAST(order_header.obi_tglcetaksj AS TIMESTAMP(0)) AS obi_tglcetaksj')
            ->selectRaw('order_header.obi_tglsertim_logistik AS obi_tglsertim_logistik')
            ->selectRaw($status.' AS ket_pin')
            ->selectRaw('handover.sti_pin AS sti_pin')
            ->selectRaw('handover.sti_receivername AS sti_receivername')
            ->distinct()
            ->orderByDesc('tgl')
            ->orderBy('notrans')
            ->orderBy('sti_pin');
    }

    private static function statusExpression(string $recid): string
    {
        return "CASE
            WHEN {$recid} LIKE 'B%' THEN 'BATAL'
            WHEN {$recid} IS NULL THEN 'BLM_SEND'
            WHEN {$recid} = '1' THEN 'PICKING'
            WHEN {$recid} = '2' THEN 'PACKING'
            WHEN {$recid} = '3' THEN 'SIAP_DSP'
            WHEN {$recid} = '5' THEN 'DSP'
            WHEN {$recid} = '6' THEN 'STRUK'
        END";
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function applyPluFilter(Builder $query, array $filters, string $productCodeColumn): void
    {
        $pluCodes = array_values(array_filter(
            array_map('trim', explode(',', $filters['plu'] ?? '')),
            static fn (string $code): bool => $code !== '',
        ));

        if ($pluCodes !== []) {
            $query->whereIn(DB::raw("SUBSTRING({$productCodeColumn}, 1, 6) || '0'"), $pluCodes);
        }
    }
}
