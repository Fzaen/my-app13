<?php

namespace App\Models\ExportModel;

use App\Models\ExportModel\SalesReport\SalesReportQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class SalesReport extends Model
{
    use SalesReportQueries;

    protected $table = 'tbtr_jualdetail';

    public $timestamps = false;

    public static function receiptCountExpression(): string
    {
        return "COUNT(DISTINCT (TO_CHAR(detail.trjd_transactiondate, 'YYYYMMDD') || detail.trjd_cashierstation || detail.trjd_create_by || detail.trjd_transactionno || detail.trjd_transactiontype))";
    }

    public static function itemCountExpression(): string
    {
        return "COUNT(DISTINCT (SUBSTR(detail.trjd_prdcd, 1, 6) || '0'))";
    }

    public static function hppExpression(): string
    {
        return "ROUND((CASE WHEN product.prd_unit = 'KG' THEN detail.trjd_baseprice / NULLIF(product.prd_frac, 0) * detail.trjd_quantity ELSE detail.trjd_baseprice * detail.trjd_quantity END) * CASE WHEN detail.trjd_transactiontype = 'R' THEN -1 ELSE 1 END, 0)";
    }

    public static function grossExpression(): string
    {
        return "ROUND(CASE WHEN detail.trjd_transactiontype = 'S' THEN detail.trjd_nominalamt ELSE detail.trjd_nominalamt * -1 END, 0)";
    }

    public static function salesNetExpression(): string
    {
        return "ROUND(CASE WHEN detail.trjd_transactiontype IN ('S', 'R') THEN (CASE WHEN detail.trjd_flagtax2 = 'Y' AND detail.trjd_create_by NOT IN ('IDM', 'RT1', 'ID1', 'ID2') THEN detail.trjd_nominalamt / CASE WHEN detail.trjd_transactiondate::date >= DATE '2022-04-01' THEN 1.11 ELSE 1.10 END ELSE detail.trjd_nominalamt END) * CASE WHEN detail.trjd_transactiontype = 'R' THEN -1 ELSE 1 END ELSE 0 END, 2)";
    }

    public static function marginExpression(): string
    {
        return "ROUND(CASE WHEN detail.trjd_transactiontype IN ('S', 'R') THEN ((CASE WHEN detail.trjd_flagtax2 = 'Y' AND detail.trjd_create_by NOT IN ('IDM', 'RT1', 'ID1', 'ID2') THEN detail.trjd_nominalamt / CASE WHEN detail.trjd_transactiondate::date >= DATE '2022-04-01' THEN 1.11 ELSE 1.10 END ELSE detail.trjd_nominalamt END) - CASE WHEN product.prd_unit = 'KG' THEN detail.trjd_quantity * detail.trjd_baseprice / 1000 ELSE detail.trjd_quantity * detail.trjd_baseprice END) * CASE WHEN detail.trjd_transactiontype = 'R' THEN -1 ELSE 1 END ELSE 0 END, 2)";
    }

    public static function quantityExpression(): string
    {
        return "ROUND((CASE WHEN product.prd_unit = 'KG' THEN detail.trjd_quantity / 1000 ELSE detail.trjd_quantity * product.prd_frac END) * CASE WHEN detail.trjd_transactiontype = 'S' THEN 1 ELSE -1 END, 2)";
    }

    public static function ppnExpression(): string
    {
        return "ROUND(CASE WHEN detail.trjd_flagtax2 = 'Y' AND detail.trjd_transactiontype IN ('S', 'R') THEN (CASE WHEN detail.trjd_transactiondate::date >= DATE '2022-04-01' THEN (CASE WHEN detail.trjd_create_by IN ('IDM', 'RT1', 'ID1', 'ID2') THEN detail.trjd_nominalamt * 0.11 / 1.11 ELSE detail.trjd_nominalamt / 11.1 * 10 * 0.11 END) ELSE (CASE WHEN detail.trjd_create_by IN ('IDM', 'RT1', 'ID1', 'ID2') THEN detail.trjd_nominalamt * 0.10 / 1.10 ELSE detail.trjd_nominalamt / 11 * 10 * 0.10 END) END) * CASE WHEN detail.trjd_transactiontype = 'R' THEN -1 ELSE 1 END ELSE 0 END)";
    }

    public static function freePpnExpression(): string
    {
        return "ROUND(COALESCE(CASE WHEN tax.kfp_statuspajak = 'BEBAS PPN' THEN detail.trjd_nominalamt * 0.11 ELSE 0 END * CASE WHEN detail.trjd_transactiontype = 'R' THEN -1 ELSE 1 END, 0))";
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function salesPerItem(array $filters, ?string $connectionName = null): Builder
    {
        $connection = DB::connection($connectionName);
        $locations = $connection->table('tbmaster_lokasi')
            ->where(function ($query): void {
                $query->where('lks_koderak', 'like', 'R%')
                    ->orWhere('lks_koderak', 'like', 'O%');
            })
            ->where(function ($query): void {
                $query->where('lks_tiperak', 'like', 'B%')
                    ->orWhere('lks_tiperak', 'like', 'I%')
                    ->orWhere('lks_tiperak', 'like', 'N%');
            });

        $details = $connection->table('tbtr_jualdetail')
            ->union($connection->table('tmp.tbtr_jualdetail'));

        $pluExpression = "SUBSTR(detail.trjd_prdcd, 1, 6) || '0'";
        $locationExpression = "location.lks_koderak || '.' || location.lks_kodesubrak || '.' || location.lks_tiperak || '.' || location.lks_shelvingrak || '.' || location.lks_nourut";

        $report = $connection->query()
            ->fromSub($details, 'detail')
            ->leftJoin('tbmaster_prodmast as product', 'detail.trjd_prdcd', '=', 'product.prd_prdcd')
            ->leftJoin('tbmaster_customer as customer', 'detail.trjd_cus_kodemember', '=', 'customer.cus_kodemember')
            ->leftJoinSub($locations, 'location', function (JoinClause $join): void {
                $join->whereRaw('SUBSTR(detail.trjd_prdcd, 1, 6) = SUBSTR(location.lks_prdcd, 1, 6)');
            })
            ->whereBetween(DB::raw('detail.trjd_transactiondate::date'), [$filters['tglstart'], $filters['tglend']]);

        self::applySalesFilters($report, $filters, $pluExpression, $connection);

        $report->selectRaw($pluExpression.' AS plu')
            ->selectRaw('product.prd_deskripsipanjang AS desk')
            ->selectRaw('product.prd_kodedivisi AS div')
            ->selectRaw('product.prd_kodedepartement AS dept')
            ->selectRaw('product.prd_kodekategoribarang AS katb')
            ->selectRaw('COUNT(DISTINCT detail.trjd_cus_kodemember) AS member')
            ->selectRaw('COUNT(DISTINCT detail.trjd_transactiondate::date) AS kunjungan')
            ->selectRaw(self::receiptCountExpression().' AS struk')
            ->selectRaw('SUM('.self::quantityExpression().') AS qty')
            ->selectRaw('SUM('.self::grossExpression().') AS gross')
            ->selectRaw('SUM('.self::salesNetExpression().') AS sales')
            ->selectRaw('SUM('.self::marginExpression().') AS margin')
            ->selectRaw($locationExpression.' AS lokasi_toko')
            ->selectRaw('location.lks_maxdisplay AS maxdis')
            ->selectRaw('location.lks_maxplano AS maxplano')
            ->groupByRaw($pluExpression)
            ->groupBy('product.prd_deskripsipanjang', 'product.prd_kodedivisi', 'product.prd_kodedepartement', 'product.prd_kodekategoribarang')
            ->groupByRaw($locationExpression)
            ->groupBy('location.lks_maxdisplay', 'location.lks_maxplano');

        return $connection->query()
            ->fromSub($report, 'sales')
            ->leftJoin('tbmaster_stock as stock', function (JoinClause $join): void {
                $join->on('stock.st_prdcd', '=', 'sales.plu')
                    ->where('stock.st_lokasi', '=', '01');
            })
            ->select([
                'sales.div',
                'sales.dept',
                'sales.katb',
                'sales.plu',
                'sales.desk',
                'sales.member',
                'sales.kunjungan',
                'sales.struk',
                'sales.qty',
                'sales.lokasi_toko',
                'sales.maxdis',
                'sales.maxplano',
                'sales.gross',
                'sales.sales',
                'sales.margin',
                'stock.st_saldoakhir as stock',
            ])
            ->orderBy('sales.plu')
            ->orderBy('sales.div')
            ->orderBy('sales.dept')
            ->orderBy('sales.katb')
            ->orderBy('sales.desk')
            ->orderBy('sales.lokasi_toko')
            ->orderBy('sales.maxdis')
            ->orderBy('sales.maxplano');
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
