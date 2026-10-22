<?php

namespace App\Models\ExportModel\SalesReport;

use App\Models\ExportModel\SalesReport as SalesReportModel;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/** @mixin SalesReportModel */
trait SalesReportQueries
{
    /**
     * @param  array<string, string>  $filters
     */
    public static function forExport(string $report, array $filters, ?string $connectionName = null): Builder
    {
        return match ($report) {
            'sales-peritem' => SalesReportModel::salesPerItem($filters, $connectionName),
            'sales-peritem-member' => self::salesPerItemMember($filters, $connectionName),
            'exportmember-peritem' => self::salesPerMemberItemDetail($filters, $connectionName),
            'sales-peritem-pertgl' => self::salesPerItemByDate($filters, $connectionName),
            'sales-peritem-perbulan' => self::salesPerItemByMonth($filters, $connectionName),
            'sales-permember' => self::salesPerMember($filters, false, $connectionName),
            'sales-permember-perbulan' => self::salesPerMember($filters, true, $connectionName),
            'sales-awb' => self::salesAwb($filters, $connectionName),
            'sales-perstruk' => self::salesPerReceipt($filters, $connectionName),
            'seles-armada-month-bytglpb-member' => self::salesArmada($filters, true, $connectionName),
            'seles-armada-month-bytglpb' => self::salesArmada($filters, false, $connectionName),
            'sales-persupplier' => self::salesPerSupplier($filters, $connectionName),
            'sales-perdepartement' => self::salesPerDepartment($filters, $connectionName),
            default => throw new \InvalidArgumentException("Unsupported sales report [{$report}]."),
        };
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerItemMember(array $filters, ?string $connectionName): Builder
    {
        $report = self::filteredSalesDetails($filters, $connectionName);
        $pluExpression = "SUBSTR(detail.trjd_prdcd, 1, 6) || '0'";
        $connection = DB::connection($connectionName);
        $pickupOrders = $connection->table('tbtr_obi_h')
            ->where('obi_recid', '6')
            ->select('obi_tglpb')
            ->selectRaw('SUBSTR(obi_nopb, 1, 7) AS nopb')
            ->selectRaw(
                "TO_CHAR(obi_tglstruk::date, 'YYYYMMDD') || obi_kdstation || obi_cashierid || obi_nostruk || obi_tipe AS transaction_key",
            );
        $stock = $connection->table('tbmaster_stock')
            ->where('st_lokasi', '01')
            ->select('st_prdcd')
            ->selectRaw('MAX(st_saldoakhir) AS stock')
            ->groupBy('st_prdcd');

        $report->leftJoinSub($pickupOrders, 'pickup', function (JoinClause $join): void {
            $join->whereRaw('pickup.transaction_key = '.self::transactionKeyExpression('detail'));
        });
        $report->leftJoinSub($stock, 'stock', function (JoinClause $join) use ($pluExpression): void {
            $join->whereRaw('stock.st_prdcd = '.$pluExpression);
        });

        return $report
            ->selectRaw("TO_CHAR(pickup.obi_tglpb, 'DD-MON-YY') AS tglpb")
            ->selectRaw('pickup.nopb AS nopb')
            ->selectRaw("TO_CHAR(detail.trjd_transactiondate, 'DD-MON-YY HH24:MI:SS') AS tglstruk")
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw($pluExpression.' AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('detail.trjd_cus_kodemember AS member')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('COUNT(DISTINCT detail.trjd_transactiondate::date) AS kunjungan')
            ->selectRaw(SalesReportModel::receiptCountExpression().' AS struk')
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->selectRaw('MAX(stock.stock) AS stock')
            ->groupByRaw("TO_CHAR(pickup.obi_tglpb, 'DD-MON-YY')")
            ->groupBy('pickup.nopb')
            ->groupByRaw("TO_CHAR(detail.trjd_transactiondate, 'DD-MON-YY HH24:MI:SS')")
            ->groupBy('product.prd_kodedivisi', 'product.prd_kodedepartement', 'product.prd_kodekategoribarang')
            ->groupByRaw($pluExpression)
            ->groupBy('product.prd_deskripsipanjang', 'detail.trjd_cus_kodemember', 'customer.cus_namamember')
            ->groupBy('stock.stock')
            ->orderBy('tglstruk')
            ->orderBy('plu')
            ->orderBy('member');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerMemberItemDetail(array $filters, ?string $connectionName): Builder
    {
        $report = self::filteredSalesDetails($filters, $connectionName);

        return $report
            ->selectRaw('detail.trjd_cus_kodemember AS kode')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('detail.trjd_prdcd AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS deskripsi')
            ->selectRaw('detail.trjd_create_by AS kasir')
            ->selectRaw('detail.trjd_cashierstation AS kassa')
            ->selectRaw('detail.trjd_transactionno AS struk')
            ->selectRaw('detail.trjd_quantity AS qty')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS rupiah')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->selectRaw('detail.trjd_transactiondate AS tanggalbelanja')
            ->groupBy(
                'detail.trjd_cus_kodemember',
                'customer.cus_namamember',
                'detail.trjd_prdcd',
                'product.prd_deskripsipanjang',
                'detail.trjd_create_by',
                'detail.trjd_cashierstation',
                'detail.trjd_transactionno',
                'detail.trjd_quantity',
                'detail.trjd_transactiondate',
            )
            ->orderByDesc('detail.trjd_transactiondate');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerItemByDate(array $filters, ?string $connectionName): Builder
    {
        return self::salesPerItemByPeriod($filters, 'DD-MON-YY', 'tgl', $connectionName);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerItemByMonth(array $filters, ?string $connectionName): Builder
    {
        return self::salesPerItemByPeriod($filters, 'YYYY-MM', 'bln', $connectionName);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerItemByPeriod(
        array $filters,
        string $dateFormat,
        string $dateHeading,
        ?string $connectionName,
    ): Builder {
        $periodExpression = "TO_CHAR(detail.trjd_transactiondate, '{$dateFormat}')";
        $report = self::filteredSalesDetails($filters, $connectionName);

        return $report
            ->selectRaw($periodExpression.' AS '.$dateHeading)
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw("SUBSTR(detail.trjd_prdcd, 1, 6) || '0' AS plu")
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('COUNT(DISTINCT detail.trjd_cus_kodemember) AS member')
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->groupByRaw($periodExpression)
            ->groupBy('product.prd_kodedivisi', 'product.prd_kodedepartement', 'product.prd_kodekategoribarang')
            ->groupByRaw("SUBSTR(detail.trjd_prdcd, 1, 6) || '0'")
            ->groupBy('product.prd_deskripsipanjang')
            ->orderBy($dateHeading)
            ->orderBy('plu');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerMember(array $filters, bool $monthly, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $report = self::filteredSalesDetails($filters, $connectionName)
            ->leftJoin('tbmaster_customercrm as crm', 'customer.cus_kodemember', '=', 'crm.crm_kodemember')
            ->leftJoin('tbmaster_outlet as outlet', 'customer.cus_kodeoutlet', '=', 'outlet.out_kodeoutlet')
            ->leftJoin('tbmaster_suboutlet as sub_outlet', 'customer.cus_kodesuboutlet', '=', 'sub_outlet.sub_kodesuboutlet');
        $periodExpression = "TO_CHAR(detail.trjd_transactiondate, 'YYYY-MM')";
        $virtualSales = $connection->table('tbtr_virtual')
            ->select('vir_kodemember')
            ->selectRaw('SUM(vir_amount) AS isk')
            ->whereBetween(DB::raw('vir_transactiondate::date'), [$filters['tglstart'], $filters['tglend']])
            ->groupBy('vir_kodemember');

        $report->leftJoinSub($virtualSales, 'virtual_sales', 'customer.cus_kodemember', '=', 'virtual_sales.vir_kodemember');

        if ($monthly) {
            $report->selectRaw($periodExpression.' AS bulan')
                ->groupByRaw($periodExpression);
        }

        $report
            ->selectRaw('detail.trjd_cus_kodemember AS member')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('COALESCE(crm.crm_alamatusaha1, customer.cus_alamatmember5) AS alamat')
            ->selectRaw('COALESCE(crm.crm_alamatusaha4, customer.cus_alamatmember8) AS kelurahan')
            ->selectRaw('COALESCE(crm.crm_alamatusaha2, customer.cus_alamatmember6) AS kota')
            ->selectRaw('COALESCE(crm.crm_alamatusaha3, customer.cus_alamatmember7) AS kdpos')
            ->selectRaw('customer.cus_hpmember AS hp')
            ->selectRaw('customer.cus_jarak AS jarak')
            ->selectRaw("TO_CHAR(customer.cus_tglregistrasi, 'DD-MON-YY') AS tglregistrasi")
            ->selectRaw('customer.cus_kodeoutlet AS outlet')
            ->selectRaw('customer.cus_kodesuboutlet AS sub_outlet')
            ->selectRaw('outlet.out_namaoutlet AS namaoutlet')
            ->selectRaw('sub_outlet.sub_namasuboutlet AS namasuboutlet')
            ->selectRaw('COUNT(DISTINCT detail.trjd_transactiondate::date) AS kunjungan');

        if (! $monthly) {
            $report->selectRaw(SalesReportModel::receiptCountExpression().' AS struk');
        }

        return $report
            ->selectRaw(SalesReportModel::itemCountExpression().' AS produk')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS dtl_gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS dtl_netto')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS dtl_margin')
            ->selectRaw('MAX(virtual_sales.isk) AS isk')
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->groupBy(
                'detail.trjd_cus_kodemember',
                'customer.cus_namamember',
                'crm.crm_alamatusaha1',
                'customer.cus_alamatmember5',
                'crm.crm_alamatusaha4',
                'customer.cus_alamatmember8',
                'crm.crm_alamatusaha2',
                'customer.cus_alamatmember6',
                'crm.crm_alamatusaha3',
                'customer.cus_alamatmember7',
                'customer.cus_hpmember',
                'customer.cus_jarak',
                'customer.cus_tglregistrasi',
                'customer.cus_kodeoutlet',
                'customer.cus_kodesuboutlet',
                'outlet.out_namaoutlet',
                'sub_outlet.sub_namasuboutlet',
                'virtual_sales.isk',
            )
            ->orderBy('member');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerSupplier(array $filters, ?string $connectionName): Builder
    {
        $report = self::filteredSalesDetails($filters, $connectionName)
            ->leftJoin('tbmaster_supplier as supplier', 'product.prd_kodesupplier', '=', 'supplier.sup_kodesupplier');
        $periodExpression = "TO_CHAR(detail.trjd_transactiondate::date, 'YYYY-MM')";

        return $report
            ->selectRaw($periodExpression.' AS bulan')
            ->selectRaw('product.prd_kodesupplier AS kodesupplier')
            ->selectRaw('supplier.sup_namasupplier AS namasupplier')
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->selectRaw(SalesReportModel::itemCountExpression().' AS item')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->groupByRaw($periodExpression)
            ->groupBy('product.prd_kodesupplier', 'supplier.sup_namasupplier')
            ->orderBy('bulan')
            ->orderBy('kodesupplier');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerDepartment(array $filters, ?string $connectionName): Builder
    {
        $report = self::filteredSalesDetails($filters, $connectionName)
            ->leftJoin('tbmaster_departement as department', function (JoinClause $join): void {
                $join->on('department.dep_kodedivisi', '=', 'product.prd_kodedivisi')
                    ->on('department.dep_kodedepartement', '=', 'product.prd_kodedepartement');
            });
        $periodExpression = "TO_CHAR(detail.trjd_transactiondate::date, 'YYYY-MM')";

        return $report
            ->selectRaw($periodExpression.' AS bulan')
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('department.dep_namadepartement AS namadepartement')
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->selectRaw(SalesReportModel::itemCountExpression().' AS item')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->groupByRaw($periodExpression)
            ->groupBy('product.prd_kodedivisi', 'product.prd_kodedepartement', 'department.dep_namadepartement')
            ->orderBy('bulan')
            ->orderBy('div')
            ->orderBy('dept');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesAwb(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);

        return $connection->query()
            ->fromSub(self::onlineOrderSales($filters, $connectionName), 'online_sales')
            ->select([
                'online_sales.nopb',
                'online_sales.tglpb',
                'online_sales.member',
                'online_sales.nama',
                'online_sales.noawb',
                'online_sales.ongkir_customer',
                'online_sales.ongkir_potongan',
                'online_sales.ongkir_total',
                'online_sales.nolisting',
                'online_sales.tglstruk_format as tglstruk',
                'online_sales.ttlorder',
                'online_sales.ttlppn',
                'online_sales.ttldiskon',
                'online_sales.itemorder',
                'online_sales.realorder',
                'online_sales.realppn',
                'online_sales.real_diskon',
                'online_sales.realitem',
                'online_sales.kdekspedisi',
                'online_sales.jrkekspedisi',
                'online_sales.userambil',
                'online_sales.tglambil',
                'online_sales.tipebayar',
                'online_sales.trxid',
                'online_sales.gross',
                'online_sales.sales',
                'online_sales.margin',
                'online_sales.qty',
                'online_sales.dsp_rph',
            ])
            ->orderBy('online_sales.tglpb')
            ->orderBy('online_sales.nopb');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesPerReceipt(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);

        return $connection->query()
            ->fromSub(self::onlineOrderSales($filters, $connectionName), 'online_sales')
            ->select([
                'online_sales.tglpb',
                'online_sales.nopb',
                'online_sales.tglstruk_format as tglstruk',
                'online_sales.member',
                'online_sales.nama',
                'online_sales.nostruk',
                'online_sales.produk',
                'online_sales.qty',
                'online_sales.gross',
                'online_sales.sales',
                'online_sales.margin',
            ])
            ->whereRaw('COALESCE(online_sales.sales, 0) <> 0')
            ->orderBy('online_sales.nostruk');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function salesArmada(array $filters, bool $byMember, ?string $connectionName): Builder
    {
        $orders = self::onlineOrderSales($filters, $connectionName);
        $report = DB::connection($connectionName)->query()
            ->fromSub($orders, 'online_sales')
            ->selectRaw("TO_CHAR(online_sales.tglpb, 'YYYYMM') AS obi_tglpb")
            ->selectRaw("CASE WHEN online_sales.kdekspedisi LIKE 'Ambil%' THEN 'AT' ELSE 'Kirim' END AS ekspedisi");

        if ($byMember) {
            $report->addSelect('online_sales.member AS kodemember', 'online_sales.nama')
                ->groupBy('online_sales.member', 'online_sales.nama');
        }

        $report
            ->selectRaw('COUNT(DISTINCT online_sales.member) AS member')
            ->selectRaw('COUNT(DISTINCT online_sales.nopb) AS jumlah_pb')
            ->selectRaw('ROUND(SUM(online_sales.gross), 0) AS gross')
            ->selectRaw('ROUND(SUM(online_sales.sales), 0) AS netto')
            ->selectRaw('ROUND(SUM(online_sales.margin), 0) AS margin')
            ->selectRaw('ROUND(SUM(online_sales.margin) / NULLIF(SUM(online_sales.sales), 0) * 100, 2) AS prsnt')
            ->selectRaw('COALESCE(SUM(online_sales.ongkir_potongan), 0) AS ongkir')
            ->selectRaw('ROUND(SUM(online_sales.margin) - COALESCE(SUM(online_sales.ongkir_potongan), 0)) AS margin_afs')
            ->selectRaw('ROUND((SUM(online_sales.margin) - COALESCE(SUM(online_sales.ongkir_potongan), 0)) / NULLIF(SUM(online_sales.sales), 0) * 100, 2) AS prsnt_mrgnt')
            ->groupByRaw("TO_CHAR(online_sales.tglpb, 'YYYYMM')")
            ->groupByRaw("CASE WHEN online_sales.kdekspedisi LIKE 'Ambil%' THEN 'AT' ELSE 'Kirim' END");

        return $report
            ->orderBy('obi_tglpb')
            ->orderBy('ekspedisi');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function onlineOrderSales(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $details = self::filteredSalesDetails($filters, $connectionName);
        $transactionKey = self::transactionKeyExpression('detail');
        $receiptSales = $details
            ->selectRaw($transactionKey.' AS transaction_key')
            ->selectRaw("COUNT(DISTINCT SUBSTR(detail.trjd_prdcd, 1, 6) || '0') AS produk")
            ->selectRaw('SUM('.SalesReportModel::quantityExpression().') AS qty')
            ->selectRaw('SUM('.SalesReportModel::grossExpression().') AS gross')
            ->selectRaw('SUM('.SalesReportModel::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.SalesReportModel::marginExpression().') AS margin')
            ->groupByRaw($transactionKey);
        $awb = $connection->table('tbtr_awb_ipp')
            ->select('awi_nopb', 'awi_noorder')
            ->selectRaw('MAX(awi_noawb) AS awi_noawb')
            ->selectRaw('MAX(awi_cost) AS awi_cost')
            ->whereNull('awi_status')
            ->groupBy('awi_nopb', 'awi_noorder');
        $dsp = $connection->table('tbtr_dsp_spi')
            ->select('dsp_nopb')
            ->selectRaw('MAX(dsp_nolisting) AS dsp_nolisting')
            ->selectRaw('MAX(COALESCE(dsp_totaldsp, dsp_totalbayar)) AS dsp_rph')
            ->groupBy('dsp_nopb');
        $headerTransactionKey = "TO_CHAR(order_header.obi_tglstruk, 'YYYYMMDD') || order_header.obi_kdstation || order_header.obi_cashierid || order_header.obi_nostruk || order_header.obi_tipe";

        return $connection->table('tbtr_obi_h as order_header')
            ->leftJoin('tbmaster_customer as customer', 'order_header.obi_kdmember', '=', 'customer.cus_kodemember')
            ->leftJoinSub($awb, 'awb', function (JoinClause $join): void {
                $join->on('awb.awi_nopb', '=', 'order_header.obi_nopb')
                    ->on('awb.awi_noorder', '=', 'order_header.obi_trxid');
            })
            ->leftJoinSub($dsp, 'dsp', 'dsp.dsp_nopb', '=', 'order_header.obi_nopb')
            ->leftJoinSub($receiptSales, 'receipt_sales', function (JoinClause $join) use ($headerTransactionKey): void {
                $join->whereRaw('receipt_sales.transaction_key = '.$headerTransactionKey);
            })
            ->where('order_header.obi_recid', '6')
            ->whereBetween(DB::raw('order_header.obi_tglpb::date'), [$filters['tglstart'], $filters['tglend']])
            ->selectRaw('order_header.obi_nopb AS nopb')
            ->selectRaw('order_header.obi_tglpb AS tglpb')
            ->selectRaw('order_header.obi_tglstruk AS tglstruk')
            ->selectRaw('order_header.obi_kdmember AS member')
            ->selectRaw('customer.cus_namamember AS nama')
            ->selectRaw('order_header.obi_nostruk AS nostruk')
            ->selectRaw('awb.awi_noawb AS noawb')
            ->selectRaw('COALESCE(order_header.obi_ekspedisi, 0) AS ongkir_customer')
            ->selectRaw('COALESCE(awb.awi_cost, 0) - COALESCE(order_header.obi_ekspedisi, 0) AS ongkir_potongan')
            ->selectRaw('COALESCE(awb.awi_cost, 0) AS ongkir_total')
            ->selectRaw('dsp.dsp_nolisting AS nolisting')
            ->selectRaw('order_header.obi_ttlorder AS ttlorder')
            ->selectRaw('order_header.obi_ttlppn AS ttlppn')
            ->selectRaw('order_header.obi_ttldiskon AS ttldiskon')
            ->selectRaw('order_header.obi_itemorder AS itemorder')
            ->selectRaw('order_header.obi_realorder AS realorder')
            ->selectRaw('order_header.obi_realppn AS realppn')
            ->selectRaw('order_header.obi_realdiskon AS real_diskon')
            ->selectRaw('order_header.obi_realitem AS realitem')
            ->selectRaw('order_header.obi_kdekspedisi AS kdekspedisi')
            ->selectRaw('order_header.obi_jrkekspedisi AS jrkekspedisi')
            ->selectRaw('order_header.obi_userambil AS userambil')
            ->selectRaw('order_header.obi_tglambil AS tglambil')
            ->selectRaw('order_header.obi_tipebayar AS tipebayar')
            ->selectRaw('order_header.obi_trxid AS trxid')
            ->selectRaw('COALESCE(receipt_sales.produk, 0) AS produk')
            ->selectRaw('COALESCE(receipt_sales.qty, 0) AS qty')
            ->selectRaw('COALESCE(receipt_sales.gross, 0) AS gross')
            ->selectRaw('COALESCE(receipt_sales.sales, 0) AS sales')
            ->selectRaw('COALESCE(receipt_sales.margin, 0) AS margin')
            ->selectRaw('COALESCE(dsp.dsp_rph, 0) AS dsp_rph')
            ->selectRaw("TO_CHAR(order_header.obi_tglstruk, 'DD-MON-YY') AS tglstruk_format");
    }

    private static function transactionKeyExpression(string $alias): string
    {
        return "TO_CHAR({$alias}.trjd_transactiondate, 'YYYYMMDD') || {$alias}.trjd_cashierstation || {$alias}.trjd_create_by || {$alias}.trjd_transactionno || {$alias}.trjd_transactiontype";
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function filteredSalesDetails(array $filters, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);
        $details = $connection->table('tbtr_jualdetail')
            ->union($connection->table('tmp.tbtr_jualdetail'));
        $pluExpression = "SUBSTR(detail.trjd_prdcd, 1, 6) || '0'";
        $report = $connection->query()
            ->fromSub($details, 'detail')
            ->leftJoin('tbmaster_prodmast as product', 'detail.trjd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_customer as customer', 'detail.trjd_cus_kodemember', '=', 'customer.cus_kodemember')
            ->whereBetween(DB::raw('detail.trjd_transactiondate::date'), [$filters['tglstart'], $filters['tglend']]);

        self::applySalesFilters($report, $filters, $pluExpression, $connection);

        return $report;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function applySalesFilters(
        Builder $report,
        array $filters,
        string $pluExpression,
        Connection $connection,
    ): void {
        $pluCodes = SalesReportModel::commaSeparatedValues($filters['plu'] ?? '');
        if ($pluCodes !== []) {
            $report->whereIn(DB::raw($pluExpression), $pluCodes);
        }

        $memberCodes = SalesReportModel::commaSeparatedValues($filters['member'] ?? '');
        if ($memberCodes !== []) {
            $report->whereIn('detail.trjd_cus_kodemember', $memberCodes);
        }

        $supplierCodes = SalesReportModel::commaSeparatedValues($filters['supplier'] ?? '');
        if ($supplierCodes !== []) {
            $report->whereIn('product.prd_kodesupplier', $supplierCodes);
        }

        if (($filters['outlet'] ?? '') !== '') {
            $report->where('customer.cus_kodeoutlet', $filters['outlet']);
        }

        if (($filters['sub_outlet'] ?? '') !== '') {
            $report->where('customer.cus_kodesuboutlet', $filters['sub_outlet']);
        }

        if (($filters['divisi'] ?? '') !== '') {
            $report->where('detail.trjd_divisioncode', $filters['divisi']);
        }

        if (($filters['departement'] ?? '') !== '') {
            $report->whereRaw('SUBSTR(detail.trjd_division, 1, 2) = ?', [$filters['departement']]);
        }

        if (($filters['larangan'] ?? '') === 'y') {
            $report->whereNotIn(
                DB::raw($pluExpression),
                $connection->table('tbmaster_plunonpromo')->select('non_prdcd'),
            );
        }

        if (($filters['esse_djarum'] ?? '') === 'y') {
            $restrictedTobaccoProducts = $connection->table('tbmaster_prodmast as tobacco')
                ->select('tobacco.prd_prdcd')
                ->where('tobacco.prd_kodedepartement', '14')
                ->whereNotIn(
                    'tobacco.prd_prdcd',
                    $connection->table('tbmaster_hargabeli')
                        ->select('hgb_prdcd')
                        ->whereIn('hgb_kodesupplier', ['S1483', 'T1175']),
                );

            $report->whereNotIn(DB::raw($pluExpression), $restrictedTobaccoProducts);
        }
    }

    /** @return list<string> */
}
