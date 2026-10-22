<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PromotionReport extends Model
{
    protected $table = 'tbtr_cashback_hdr';

    public $timestamps = false;

    /**
     * @param  array<string, string>  $filters
     */
    public static function forExport(string $report, array $filters, ?string $connectionName = null): Builder
    {
        return match ($report) {
            'promo-cb-aktif' => self::cashback($filters, false, $connectionName),
            'promo-cb-periode' => self::cashback($filters, true, $connectionName),
            'promo-detail-mypoint' => self::myPointDetail($filters, $connectionName),
            'promo-gift-aktif' => self::gift($filters, false, $connectionName),
            'promo-gift-allho' => self::allGiftPromotions($connectionName),
            'promo-gift-periode' => self::gift($filters, true, $connectionName),
            'promo-item-larangan' => self::restrictedItems($connectionName),
            'promo-mypoint' => self::myPointSummary($filters, $connectionName),
            'promo-pembatasan' => self::transactionRestrictions($connectionName),
            'promo-tidak-ada-promo' => self::productsWithoutPromotion($connectionName),
            default => throw new InvalidArgumentException("Unsupported promotion report [{$report}]."),
        };
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function cashback(array $filters, bool $period, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $issued = $connection->table('m_promosi_h')
            ->select('kd_promosi')
            ->selectRaw('SUM(kelipatan) AS alokasikeluar')
            ->groupBy('kd_promosi');
        $locations = $connection->table('tbmaster_lokasi')
            ->where(function (Builder $query): void {
                $query->where('lks_koderak', 'like', 'R%')
                    ->orWhere('lks_koderak', 'like', 'O%');
            })
            ->where(function (Builder $query): void {
                $query->where('lks_tiperak', 'like', 'B%')
                    ->orWhere('lks_tiperak', 'like', 'I%')
                    ->orWhere('lks_tiperak', 'like', 'N%');
            })
            ->select('lks_prdcd')
            ->selectRaw(
                "lks_koderak || '.' || lks_kodesubrak || '.' || lks_tiperak || '.' || lks_shelvingrak || '.' || lks_nourut AS lokasi",
            );

        $report = $connection->table('tbtr_cashback_dtl as cashback_detail')
            ->leftJoin('tbtr_cashback_hdr as cashback_header', 'cashback_detail.cbd_kodepromosi', '=', 'cashback_header.cbh_kodepromosi')
            ->leftJoin('tbmaster_prodmast as product', 'cashback_detail.cbd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbtr_cashback_alokasi as cashback_allocation', 'cashback_detail.cbd_kodepromosi', '=', 'cashback_allocation.cba_kodepromosi')
            ->leftJoinSub($issued, 'issued', 'cashback_detail.cbd_kodepromosi', '=', 'issued.kd_promosi')
            ->leftJoinSub($locations, 'location', 'cashback_detail.cbd_prdcd', '=', 'location.lks_prdcd')
            ->whereNotNull('product.prd_deskripsipanjang')
            ->whereNull('product.prd_recordid')
            ->where('cashback_header.cbh_namapromosi', 'not like', '%UNIQUE%')
            ->where('cashback_header.cbh_namapromosi', 'not like', '%UNICODE%')
            ->whereRaw("COALESCE(cashback_detail.cbd_recordid, '2') <> '1'");

        if ($period) {
            $report->whereRaw('cashback_header.cbh_tglawal::date >= ?', [$filters['tglstart']])
                ->whereRaw('cashback_header.cbh_tglakhir::date <= ?', [$filters['tglend']]);
        } else {
            $report->whereRaw('cashback_header.cbh_tglawal::date <= CURRENT_DATE')
                ->whereRaw('cashback_header.cbh_tglakhir::date >= CURRENT_DATE');
        }

        return $report
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw('cashback_detail.cbd_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('(cashback_detail.cbd_cashback + cashback_header.cbh_cashback) AS cashback')
            ->selectRaw('cashback_detail.cbd_minstruk AS minjual')
            ->selectRaw('cashback_header.cbh_minrphprodukpromo AS minproduk')
            ->selectRaw('cashback_detail.cbd_maxstruk AS maxju')
            ->selectRaw('cashback_header.cbh_maxstrkperhari AS maxrph')
            ->selectRaw('cashback_detail.cbd_redeempoint AS redeempoint')
            ->selectRaw('cashback_allocation.cba_alokasijumlah AS alokasi')
            ->selectRaw('issued.alokasikeluar AS alokasikeluar')
            ->selectRaw('(cashback_allocation.cba_alokasijumlah - issued.alokasikeluar) AS sisalokasi')
            ->selectRaw("CASE WHEN cashback_allocation.cba_platinum = '1' THEN 'Ya' ELSE 'Tidak' END AS platinum")
            ->selectRaw("CASE WHEN (cashback_allocation.cba_retailer = '1' OR cashback_allocation.cba_silver = '1' OR cashback_allocation.cba_gold1 = '1' OR cashback_allocation.cba_gold2 = '1') THEN 'Ya' ELSE 'Tidak' END AS merah")
            ->selectRaw("CASE WHEN (cashback_allocation.cba_reguler = '1' OR cashback_allocation.cba_reguler_biruplus = '1') THEN 'Ya' ELSE 'Tidak' END AS biru")
            ->selectRaw("TO_CHAR(cashback_header.cbh_tglawal, 'DD-MON-YY') AS tglawal")
            ->selectRaw("TO_CHAR(cashback_header.cbh_tglakhir, 'DD-MON-YY') AS tglakhir")
            ->selectRaw('cashback_detail.cbd_kodepromosi AS kode')
            ->selectRaw('cashback_header.cbh_namapromosi AS cbh_namapromosi')
            ->selectRaw('location.lokasi AS lokasi')
            ->orderByDesc('cashback_header.cbh_tglakhir');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function gift(array $filters, bool $period, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $issued = $connection->table('m_gift_h')
            ->select('kd_promosi')
            ->selectRaw('SUM(kelipatan) AS alokasikeluar')
            ->groupBy('kd_promosi');
        $giftDescriptions = $connection->table('tbtr_gift_hdr as gift_header')
            ->leftJoin('tbmaster_prodmast as gift_product', 'gift_header.gfh_kethadiah', '=', 'gift_product.prd_prdcd')
            ->select('gift_header.gfh_kethadiah')
            ->select('gift_product.prd_deskripsipanjang')
            ->distinct();

        $report = $connection->table('tbtr_gift_dtl as gift_detail')
            ->leftJoin('tbmaster_prodmast as product', 'gift_detail.gfd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbtr_gift_hdr as gift_header', 'gift_detail.gfd_kodepromosi', '=', 'gift_header.gfh_kodepromosi')
            ->leftJoin('tbtr_gift_alokasi as gift_allocation', 'gift_detail.gfd_kodepromosi', '=', 'gift_allocation.gfa_kodepromosi')
            ->leftJoinSub($issued, 'issued', 'gift_detail.gfd_kodepromosi', '=', 'issued.kd_promosi')
            ->leftJoinSub($giftDescriptions, 'gift_description', 'gift_header.gfh_kethadiah', '=', 'gift_description.gfh_kethadiah')
            ->whereNotNull('product.prd_deskripsipanjang');

        if ($period) {
            $report->whereRaw('gift_header.gfh_tglawal::date >= ?', [$filters['tglstart']])
                ->whereRaw('gift_header.gfh_tglakhir::date <= ?', [$filters['tglend']]);
        } else {
            $report->whereRaw('gift_header.gfh_tglawal::date <= CURRENT_DATE')
                ->whereRaw('gift_header.gfh_tglakhir::date >= CURRENT_DATE');
        }

        return $report
            ->selectRaw("TO_CHAR(gift_header.gfh_tglawal, 'DD-MON-YY') || ' s/d ' || TO_CHAR(gift_header.gfh_tglakhir, 'DD-MON-YY') AS periode")
            ->selectRaw("TO_CHAR(gift_header.gfh_tglawal, 'DD-MON-YY') AS awal")
            ->selectRaw("TO_CHAR(gift_header.gfh_tglakhir, 'DD-MON-YY') AS akhir")
            ->selectRaw('gift_header.gfh_kodepromosi AS kode')
            ->selectRaw('gift_header.gfh_namapromosi AS namapromo')
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw('gift_detail.gfd_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('gift_detail.gfd_pcs AS pcs')
            ->selectRaw('gift_detail.gfd_rph AS rph')
            ->selectRaw('gift_header.gfh_mintotsponsor AS minsponsor')
            ->selectRaw('gift_header.gfh_kethadiah AS pluhdh')
            ->selectRaw('gift_description.prd_deskripsipanjang AS hdh')
            ->selectRaw('gift_allocation.gfa_alokasijumlah AS alokasi')
            ->selectRaw('issued.alokasikeluar AS alokasikeluar')
            ->selectRaw('CASE WHEN (gift_allocation.gfa_alokasijumlah - issued.alokasikeluar) < 0 THEN 0 ELSE (gift_allocation.gfa_alokasijumlah - issued.alokasikeluar) END AS sisaalokasi')
            ->orderByDesc('gift_header.gfh_tglakhir');
    }

    private static function allGiftPromotions(?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $issued = $connection->table('m_gift_h')
            ->select('kd_promosi')
            ->selectRaw('SUM(jmlh_hadiah) AS alokasi_keluar')
            ->groupBy('kd_promosi');
        $costs = $connection->table('tbmaster_prodmast as cost_product')
            ->where('cost_product.prd_prdcd', 'like', '%1')
            ->selectRaw("SUBSTR(cost_product.prd_prdcd, 1, 6) || '0' AS plu2")
            ->selectRaw('cost_product.prd_avgcost AS acostpcs');

        return $connection->table('tbtr_gift_hdr as gift_header')
            ->leftJoin('tbmaster_prodmast as product', 'gift_header.gfh_kethadiah', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_stock as stock', function (JoinClause $join): void {
                $join->on('gift_header.gfh_kethadiah', '=', 'stock.st_prdcd')
                    ->where('stock.st_lokasi', '01');
            })
            ->leftJoinSub($issued, 'issued', 'gift_header.gfh_kodepromosi', '=', 'issued.kd_promosi')
            ->leftJoinSub($costs, 'costs', function (JoinClause $join): void {
                $join->whereRaw('gift_header.gfh_kethadiah = costs.plu2');
            })
            ->leftJoin('tbtr_gift_alokasi as gift_allocation', 'gift_header.gfh_kodepromosi', '=', 'gift_allocation.gfa_kodepromosi')
            ->selectRaw('gift_header.gfh_kodepromosi AS kdpromo')
            ->selectRaw('gift_header.gfh_namapromosi AS nama')
            ->selectRaw('gift_header.gfh_mintotbelanja AS min_belanja')
            ->selectRaw('gift_header.gfh_mintotsponsor AS min_sponsor')
            ->selectRaw('gift_header.gfh_maxjmlhari AS maxperhari')
            ->selectRaw('gift_header.gfh_maxfrekhari AS maxfrek_perhari')
            ->selectRaw('gift_header.gfh_kethadiah AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('product.prd_frac AS frac')
            ->selectRaw('gift_header.gfh_jmlhadiah AS jml_hadiah')
            ->selectRaw('gift_header.gfh_tglawal AS awal')
            ->selectRaw('gift_header.gfh_tglakhir AS akhir')
            ->selectRaw('gift_allocation.gfa_alokasijumlah AS jumlah_alokasi')
            ->selectRaw('issued.alokasi_keluar AS alokasi_keluar')
            ->selectRaw('costs.acostpcs AS acostpcs')
            ->selectRaw('(COALESCE(gift_allocation.gfa_alokasijumlah, 0) - COALESCE(issued.alokasi_keluar, 0)) AS sisa_alokasi')
            ->selectRaw('gift_header.gfh_kodeperjanjian AS gfh_kodeperjanjian')
            ->selectRaw('stock.st_saldoakhir AS stock')
            ->distinct()
            ->orderBy('akhir');
    }

    private static function restrictedItems(?string $connectionName): Builder
    {
        return DB::connection($connectionName)->table('tbmaster_plunonpromo as nonpromo')
            ->leftJoin('tbmaster_prodmast as product', 'nonpromo.non_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_departement as department', 'product.prd_kodedepartement', '=', 'department.dep_kodedepartement')
            ->leftJoin('tbmaster_kategori as category', function (JoinClause $join): void {
                $join->whereRaw(
                    'product.prd_kodedepartement || product.prd_kodekategoribarang = category.kat_kodedepartement || category.kat_kodekategori',
                );
            })
            ->selectRaw('nonpromo.non_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('nonpromo.non_recordid AS non_recordid')
            ->selectRaw('product.prd_kodedivisi AS prd_kodedivisi')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw('department.dep_namadepartement AS dep_namadepartement')
            ->selectRaw('category.kat_namakategori AS kat_namakategori')
            ->selectRaw('nonpromo.non_create_by AS non_create_by')
            ->selectRaw('nonpromo.non_create_dt AS non_create_dt')
            ->selectRaw('nonpromo.non_modify_by AS non_modify_by')
            ->selectRaw('nonpromo.non_modify_dt AS non_modify_dt')
            ->selectRaw('product.prd_kodeigr AS prd_kodeigr');
    }

    private static function transactionRestrictions(?string $connectionName): Builder
    {
        return DB::connection($connectionName)->table('tbtabel_maxtransaksi as restriction')
            ->leftJoin('tbmaster_prodmast as product', 'restriction.mtr_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_stock as stock', function (JoinClause $join): void {
                $join->on('restriction.mtr_prdcd', '=', 'stock.st_prdcd')
                    ->where('stock.st_lokasi', '01');
            })
            ->selectRaw('restriction.mtr_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('product.prd_frac AS frac')
            ->selectRaw('stock.st_saldoakhir AS stock_pcs')
            ->selectRaw('(stock.st_saldoakhir / product.prd_frac) AS stock_ctn')
            ->selectRaw('restriction.mtr_create_by AS penginput')
            ->selectRaw('restriction.mtr_qtyregulerbiru AS biru')
            ->selectRaw('restriction.mtr_qtyregulerbiruplus AS biruplus')
            ->selectRaw('restriction.mtr_qtyfreepass AS freepass')
            ->selectRaw('restriction.mtr_qtyretailermerah AS retailermerah')
            ->selectRaw('restriction.mtr_qtysilver AS silver')
            ->selectRaw('restriction.mtr_qtygold1 AS gold1')
            ->selectRaw('restriction.mtr_qtygold2 AS gold2')
            ->selectRaw('restriction.mtr_qtygold3 AS gold3')
            ->selectRaw('restriction.mtr_qtyplatinum AS platinum');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function myPointSummary(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $igrRedemptions = $connection->table('tmp.igrbms_penukaranmypoin as redemption')
            ->whereBetween(DB::raw('redemption.pot_create_dt::date'), [$filters['tglstart'], $filters['tglend']])
            ->select('redemption.pot_kodemember')
            ->selectRaw('SUM(redemption.pot_penukaranpoint) AS igr_penukaran')
            ->selectRaw('SUM(redemption.pot_redeempoint) AS igr_redeem')
            ->groupBy('redemption.pot_kodemember');
        $igrEarnings = $connection->table('tmp.igrbms_perolehanmypoin as earning')
            ->whereBetween(DB::raw('earning.por_create_dt::date'), [$filters['tglstart'], $filters['tglend']])
            ->select('earning.por_kodemember')
            ->selectRaw("SUM(CASE WHEN earning.por_flagupdate = 'Y' THEN earning.por_perolehanpoint ELSE 0 END) AS igr_perolehan")
            ->selectRaw("SUM(CASE WHEN COALESCE(earning.por_flagupdate, 'N') <> 'Y' THEN earning.por_perolehanpoint ELSE 0 END) AS igr_perolehan_gagal")
            ->groupBy('earning.por_kodemember');
        $spiRedemptions = $connection->table('tbtr_penukaranmypoin as redemption')
            ->whereBetween(DB::raw('redemption.pot_create_dt::date'), [$filters['tglstart'], $filters['tglend']])
            ->select('redemption.pot_kodemember')
            ->selectRaw('SUM(redemption.pot_penukaranpoint) AS spi_penukaran')
            ->selectRaw('SUM(redemption.pot_redeempoint) AS spi_redeem')
            ->groupBy('redemption.pot_kodemember');
        $spiEarnings = $connection->table('tbtr_perolehanmypoin as earning')
            ->whereBetween(DB::raw('earning.por_create_dt::date'), [$filters['tglstart'], $filters['tglend']])
            ->select('earning.por_kodemember')
            ->selectRaw("SUM(CASE WHEN earning.por_flagupdate = 'Y' THEN earning.por_perolehanpoint ELSE 0 END) AS spi_perolehan")
            ->selectRaw("SUM(CASE WHEN COALESCE(earning.por_flagupdate, 'N') <> 'Y' THEN earning.por_perolehanpoint ELSE 0 END) AS spi_perolehan_gagal")
            ->groupBy('earning.por_kodemember');

        $summary = $connection->table('tbmaster_customer as customer')
            ->leftJoinSub($igrRedemptions, 'igr_redemptions', 'customer.cus_kodemember', '=', 'igr_redemptions.pot_kodemember')
            ->leftJoinSub($igrEarnings, 'igr_earnings', 'customer.cus_kodemember', '=', 'igr_earnings.por_kodemember')
            ->leftJoinSub($spiRedemptions, 'spi_redemptions', 'customer.cus_kodemember', '=', 'spi_redemptions.pot_kodemember')
            ->leftJoinSub($spiEarnings, 'spi_earnings', 'customer.cus_kodemember', '=', 'spi_earnings.por_kodemember')
            ->where('customer.cus_kodeigr', '1M')
            ->whereNotNull('customer.cus_tglregistrasi')
            ->selectRaw('customer.cus_kodemember AS trjd_cus_kodemember')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('igr_earnings.igr_perolehan AS igr_perolehan')
            ->selectRaw('spi_earnings.spi_perolehan AS spi_perolehan')
            ->selectRaw('igr_earnings.igr_perolehan_gagal AS igr_perolehan_gagal')
            ->selectRaw('spi_earnings.spi_perolehan_gagal AS spi_perolehan_gagal')
            ->selectRaw('igr_redemptions.igr_penukaran AS igr_penukaran')
            ->selectRaw('igr_redemptions.igr_redeem AS igr_redeem')
            ->selectRaw('spi_redemptions.spi_penukaran AS spi_penukaran')
            ->selectRaw('spi_redemptions.spi_redeem AS spi_redeem')
            ->selectRaw('(COALESCE(igr_earnings.igr_perolehan, 0) + COALESCE(spi_earnings.spi_perolehan, 0)) - (COALESCE(igr_redemptions.igr_penukaran, 0) + COALESCE(igr_redemptions.igr_redeem, 0) + COALESCE(spi_redemptions.spi_penukaran, 0) + COALESCE(spi_redemptions.spi_redeem, 0)) AS sisa');

        $report = $connection->query()
            ->fromSub($summary, 'poin')
            ->whereNotNull('poin.sisa');
        $members = self::commaSeparatedValues($filters['member'] ?? '');

        if ($members !== []) {
            $report->whereIn('poin.trjd_cus_kodemember', $members);
        }

        return $report->select([
            'poin.trjd_cus_kodemember',
            'poin.nama',
            'poin.igr_perolehan',
            'poin.spi_perolehan',
            'poin.igr_perolehan_gagal',
            'poin.spi_perolehan_gagal',
            'poin.igr_penukaran',
            'poin.igr_redeem',
            'poin.spi_penukaran',
            'poin.spi_redeem',
            'poin.sisa',
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function myPointDetail(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $member = $filters['member'];
        $redemptions = $connection->table('tbtr_penukaranmypoin')
            ->where('pot_kodemember', $member)
            ->selectRaw("'penukaran spi' AS ket")
            ->selectRaw('pot_kodeigr AS toko')
            ->selectRaw('pot_kodemember AS member')
            ->selectRaw('pot_kodetransaksi AS trx')
            ->selectRaw('pot_penukaranpoint AS poin')
            ->selectRaw('pot_create_dt AS create_dt')
            ->selectRaw('pot_modify_dt AS modify_dt')
            ->selectRaw('pot_redeempoint AS redeempoin')
            ->selectRaw('pot_kodemypoin AS no_hp')
            ->selectRaw('NULL AS flagupdate');
        $earnings = $connection->table('tbtr_perolehanmypoin')
            ->where('por_kodemember', $member)
            ->selectRaw("'perolehan spi' AS ket")
            ->selectRaw('por_kodeigr AS toko')
            ->selectRaw('por_kodemember AS member')
            ->selectRaw('por_kodetransaksi AS trx')
            ->selectRaw('por_perolehanpoint AS poin')
            ->selectRaw('por_create_dt AS create_dt')
            ->selectRaw('por_modify_dt AS modify_dt')
            ->selectRaw('NULL AS redeempoin')
            ->selectRaw('por_kodemypoin AS no_hp')
            ->selectRaw('por_flagupdate AS flagupdate');
        $pointDetails = $redemptions->unionAll($earnings);

        return $connection->query()
            ->fromSub($pointDetails, 'point_detail')
            ->selectRaw('point_detail.ket AS ket')
            ->selectRaw('point_detail.toko AS toko')
            ->selectRaw('point_detail.member AS member')
            ->selectRaw('point_detail.trx AS trx')
            ->selectRaw('point_detail.poin AS poin')
            ->selectRaw('point_detail.create_dt AS create_dt')
            ->selectRaw('point_detail.modify_dt AS modify_dt')
            ->selectRaw('point_detail.redeempoin AS redeempoin')
            ->selectRaw('point_detail.no_hp AS no_hp')
            ->selectRaw("CASE WHEN COALESCE(point_detail.flagupdate, 'N') <> 'Y' AND point_detail.ket LIKE '%perolehan%' THEN COALESCE(point_detail.flagupdate, 'N') || ' Gagal' ELSE NULL END AS status")
            ->orderByDesc('point_detail.create_dt');
    }

    private static function productsWithoutPromotion(?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $promotions2025 = $connection->table('tbtr_cashback_dtl as detail')
            ->leftJoin('tbtr_cashback_hdr as header', 'detail.cbd_kodepromosi', '=', 'header.cbh_kodepromosi')
            ->where('detail.cbd_cashback', '>', 0)
            ->whereRaw("header.cbh_tglawal::date >= DATE '2025-01-01'")
            ->select('detail.cbd_prdcd')
            ->selectRaw('COUNT(DISTINCT detail.cbd_kodepromosi) AS promo_2025')
            ->groupBy('detail.cbd_prdcd');
        $promotions2024 = $connection->table('tbtr_cashback_dtl as detail')
            ->leftJoin('tbtr_cashback_hdr as header', 'detail.cbd_kodepromosi', '=', 'header.cbh_kodepromosi')
            ->where('detail.cbd_cashback', '>', 0)
            ->whereRaw("header.cbh_tglawal::date BETWEEN DATE '2024-01-01' AND DATE '2024-12-31'")
            ->select('detail.cbd_prdcd')
            ->selectRaw('COUNT(DISTINCT detail.cbd_kodepromosi) AS promo_2024')
            ->groupBy('detail.cbd_prdcd');

        return $connection->table('tbmaster_prodmast as product')
            ->leftJoinSub($promotions2025, 'promotions_2025', 'promotions_2025.cbd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoinSub($promotions2024, 'promotions_2024', 'promotions_2024.cbd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_stock as stock', function (JoinClause $join): void {
                $join->on('product.prd_prdcd', '=', 'stock.st_prdcd')
                    ->where('stock.st_lokasi', '01');
            })
            ->whereNull('product.prd_recordid')
            ->where('product.prd_prdcd', 'like', '%0')
            ->selectRaw('product.prd_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('stock.st_saldoakhir AS stock')
            ->selectRaw('promotions_2024.promo_2024 AS promo_2024')
            ->selectRaw('promotions_2025.promo_2025 AS promo_2025');
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<string>
     */
    private static function commaSeparatedValues(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
