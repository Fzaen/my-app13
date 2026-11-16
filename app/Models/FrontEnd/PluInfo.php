<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

class PluInfo extends Model
{
    private const PANEL_CACHE_SECONDS = 30;

    protected $table = 'tbmaster_prodmast';

    public $timestamps = false;

    /** @return Collection<int, stdClass> */
    public function searchByDescription(string $description, ?string $connectionName = null): Collection
    {
        return DB::connection($connectionName)
            ->table($this->getTable().' as product')
            ->select([
                'product.prd_prdcd as plu',
                'product.prd_deskripsipanjang as description',
                'product.prd_unit as unit',
                'product.prd_frac as fraction',
            ])
            ->where('product.prd_prdcd', 'like', '%0')
            ->whereRaw('LOWER(product.prd_deskripsipanjang) LIKE ?', [
                '%'.mb_strtolower($description).'%',
            ])
            ->orderBy('product.prd_deskripsipanjang')
            ->limit(30)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    public function suggestProducts(string $search, ?string $connectionName = null): Collection
    {
        return DB::connection($connectionName)
            ->table($this->getTable().' as product')
            ->select([
                'product.prd_prdcd as plu',
                'product.prd_deskripsipanjang as description',
                'product.prd_unit as unit',
                'product.prd_frac as fraction',
            ])
            ->where('product.prd_prdcd', 'like', '%0')
            ->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(product.prd_deskripsipanjang) LIKE ?', [
                    '%'.mb_strtolower($search).'%',
                ])->orWhere('product.prd_prdcd', 'like', '%'.trim($search).'%');
            })
            ->orderBy('product.prd_deskripsipanjang')
            ->limit(10)
            ->get();
    }

    public function findByInput(string $input, ?string $connectionName = null): ?stdClass
    {
        $plu = $this->resolvePlu($input, $connectionName);

        return $plu === null ? null : $this->product($plu, $connectionName);
    }

    public function hasProduct(string $plu, ?string $connectionName = null): bool
    {
        return DB::connection($connectionName)
            ->table($this->getTable())
            ->where('prd_prdcd', $plu)
            ->exists();
    }

    /** @return list<array{value: string, label: string}> */
    public static function panelOptions(): array
    {
        return [
            ['value' => 'barcodes', 'label' => 'Barcode'],
            ['value' => 'special-prices', 'label' => 'Harga khusus aktif'],
            ['value' => 'market-prices', 'label' => 'Harga pasar'],
            ['value' => 'locations', 'label' => 'Lokasi toko'],
            ['value' => 'cashbacks', 'label' => 'Cashback aktif'],
            ['value' => 'gifts', 'label' => 'Gift aktif'],
            ['value' => 'price-margin', 'label' => 'Harga net dan margin'],
            ['value' => 'receipts', 'label' => 'Penerimaan'],
            ['value' => 'returns', 'label' => 'Retur'],
            ['value' => 'purchase-orders', 'label' => 'PO'],
            ['value' => 'cashback-history', 'label' => 'Riwayat cashback'],
            ['value' => 'gift-history', 'label' => 'Riwayat gift'],
            ['value' => 'restrictions', 'label' => 'Pembatasan'],
            ['value' => 'spb', 'label' => 'SPB'],
            ['value' => 'slp', 'label' => 'SLP'],
            ['value' => 'stock-planogram', 'label' => 'LPP vs plano'],
            ['value' => 'sales-monthly', 'label' => 'Penjualan per bulan (ringkasan)'],
            ['value' => 'sales', 'label' => 'Riwayat transaksi sales'],
            ['value' => 'so-ic', 'label' => 'SO IC'],
            ['value' => 'so-daily', 'label' => 'SO harian'],
            ['value' => 'status-changes', 'label' => 'Ubah status'],
            ['value' => 'picking', 'label' => 'Picking'],
            ['value' => 'dsi', 'label' => 'DSI 3 bulan'],
            ['value' => 'promotion-prices', 'label' => 'Harga promo'],
        ];
    }

    /** @return Collection<int, stdClass> */
    public function panelData(string $panel, string $plu, ?string $connectionName = null): Collection
    {
        $cacheKey = 'plu-info:panel:v2:'.hash('sha256', implode('|', [
            $connectionName ?? (string) config('database.default'),
            $panel,
            $plu,
            now()->toDateString(),
        ]));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::PANEL_CACHE_SECONDS),
            function () use ($panel, $plu, $connectionName): Collection {
                $connection = DB::connection($connectionName);
                $pluRange = $this->pluFamilyBounds($plu);

                return match ($panel) {
                    'barcodes' => $connection->table('tbmaster_barcode')
                        ->select([
                            'brc_prdcd as plu',
                            'brc_barcode as barcode',
                            'brc_status as status',
                            'brc_flag_aktivasi as activation_status',
                            'brc_create_by as created_by',
                            'brc_create_dt as created_at',
                            'brc_modify_by as modified_by',
                            'brc_modify_dt as modified_at',
                        ])
                        ->whereBetween('brc_prdcd', $pluRange)
                        ->orderByDesc('brc_create_dt')
                        ->limit(50)
                        ->get(),
                    'special-prices' => $connection->table('tbtr_hargakhusus as special_price')
                        ->leftJoin('tbmaster_prodmast as product', 'special_price.hgk_prdcd', '=', 'product.prd_prdcd')
                        ->select([
                            'special_price.hgk_prdcd as plu',
                            'product.prd_deskripsipanjang as description',
                            'product.prd_avgcost as average_cost',
                            'special_price.hgk_jenisdisc as discount_type',
                            'special_price.hgk_hrgjual as price',
                            'special_price.hgk_potonganpersen as discount_percent',
                            'special_price.hgk_potonganrph as discount_amount',
                            'special_price.hgk_hrgjualbaru as new_price',
                            'special_price.hgk_kelompokmember as member_group',
                            'special_price.hgk_alokasi as allocation',
                            'special_price.hgk_tglawal as starts_at',
                            'special_price.hgk_jamawal as starts_time',
                            'special_price.hgk_tglakhir as ends_at',
                            'special_price.hgk_jamakhir as ends_time',
                            'special_price.hgk_status as status',
                        ])
                        ->whereBetween('special_price.hgk_prdcd', $pluRange)
                        ->where('special_price.hgk_tglawal', '<', now()->addDay()->toDateString())
                        ->where('special_price.hgk_tglakhir', '>=', now()->toDateString())
                        ->orderBy('special_price.hgk_tglawal')
                        ->limit(50)
                        ->get(),
                    'market-prices' => $connection->table('tmp.spi1m_hargapasar')
                        ->select([
                            'hgp_prdcd as plu',
                            'hgp_desk as description',
                            'hgp_sumber as source',
                            'hgp_hargapasar as market_price',
                            'hgp_harganormal as regular_price',
                            'hgp_nett as net_price',
                            'hgp_acost as average_cost',
                            'hgp_tanggalbukti as receipt_date',
                            'hgp_keterangan as notes',
                            'hgp_create_dt as created_at',
                            'hgp_create_by as created_by',
                            'hgp_namafile as file_name',
                        ])
                        ->whereBetween('hgp_prdcd', $pluRange)
                        ->orderByDesc('hgp_create_dt')
                        ->limit(50)
                        ->get(),
                    'locations' => $connection->table('tbmaster_lokasi')
                        ->select([
                            'lks_prdcd as plu',
                            'lks_koderak as rack',
                            'lks_kodesubrak as subrack',
                            'lks_tiperak as rack_type',
                            'lks_shelvingrak as shelving',
                            'lks_nourut as sequence',
                            'lks_depanbelakang as front_back',
                            'lks_atasbawah as top_bottom',
                            'lks_qty as quantity',
                            'lks_maxdisplay as maximum_display',
                            'lks_expdate as expires_at',
                            'lks_room as room',
                        ])
                        ->where('lks_prdcd', $plu)
                        ->orderBy('lks_koderak')
                        ->orderBy('lks_kodesubrak')
                        ->orderBy('lks_nourut')
                        ->limit(100)
                        ->get(),
                    'cashbacks' => $connection->table('tbtr_cashback_dtl as detail')
                        ->join('tbtr_cashback_hdr as header', 'detail.cbd_kodepromosi', '=', 'header.cbh_kodepromosi')
                        ->join('tbtr_cashback_alokasi as allocation', 'detail.cbd_kodepromosi', '=', 'allocation.cba_kodepromosi')
                        ->select([
                            'header.cbh_kodepromosi as promotion_code',
                            'header.cbh_namapromosi as promotion_name',
                            'header.cbh_jenispromosi as promotion_type',
                            'header.cbh_mekanisme as mechanism',
                            'detail.cbd_prdcd as plu',
                            'detail.cbd_minstruk as minimum_quantity',
                            'detail.cbd_maxstruk as maximum_quantity',
                            'detail.cbd_cashback as item_cashback',
                            'header.cbh_cashback as receipt_cashback',
                            'header.cbh_minrphprodukpromo as minimum_product_amount',
                            'header.cbh_mintotbelanja as minimum_receipt_amount',
                            'header.cbh_tglawal as starts_at',
                            'header.cbh_tglakhir as ends_at',
                            'allocation.cba_retailer as retailer_member',
                            'allocation.cba_silver as silver_member',
                            'allocation.cba_gold1 as gold_one_member',
                        ])
                        ->whereBetween('detail.cbd_prdcd', $pluRange)
                        ->where('header.cbh_tglawal', '<', now()->addDay()->toDateString())
                        ->where('header.cbh_tglakhir', '>=', now()->toDateString())
                        ->whereRaw("COALESCE(detail.cbd_recordid, '2') <> '1'")
                        ->whereRaw("COALESCE(detail.cbd_redeempoint, '0') = '0'")
                        ->where('header.cbh_jenispromosi', '<>', '4')
                        ->where(function ($query): void {
                            $query->where('header.cbh_flagspi', 'Y')
                                ->orWhere('header.cbh_flagigr', 'Y');
                        })
                        ->where(function ($query): void {
                            $query->where('allocation.cba_retailer', '1')
                                ->orWhere('allocation.cba_silver', '1')
                                ->orWhere('allocation.cba_gold1', '1');
                        })
                        ->orderBy('header.cbh_tglakhir')
                        ->limit(50)
                        ->get(),
                    'gifts' => $connection->table('tbtr_gift_dtl as detail')
                        ->join('tbtr_gift_hdr as header', 'detail.gfd_kodepromosi', '=', 'header.gfh_kodepromosi')
                        ->leftJoin('tbtr_gift_alokasi as allocation', 'detail.gfd_kodepromosi', '=', 'allocation.gfa_kodepromosi')
                        ->select([
                            'header.gfh_kodepromosi as promotion_code',
                            'header.gfh_namapromosi as promotion_name',
                            'header.gfh_mekanisme as mechanism',
                            'detail.gfd_prdcd as plu',
                            'detail.gfd_pcs as minimum_quantity',
                            'detail.gfd_rph as minimum_amount',
                            'header.gfh_jmlhadiah as gift_quantity',
                            'header.gfh_kethadiah as gift_description',
                            'header.gfh_tglawal as starts_at',
                            'header.gfh_tglakhir as ends_at',
                            'allocation.gfa_reguler as regular_member',
                            'allocation.gfa_retailer as retailer_member',
                            'allocation.gfa_platinum as platinum_member',
                        ])
                        ->whereBetween('detail.gfd_prdcd', $pluRange)
                        ->where('header.gfh_tglawal', '<', now()->addDay()->toDateString())
                        ->where('header.gfh_tglakhir', '>=', now()->toDateString())
                        ->orderBy('header.gfh_tglakhir')
                        ->limit(50)
                        ->get(),
                    'receipts' => $this->stockMovements($connectionName, $pluRange, ['B', 'L', 'I']),
                    'returns' => $this->stockMovements($connectionName, $pluRange, ['K']),
                    'status-changes' => $connection->table('tbtr_mstran_d')
                        ->select([
                            'mstd_nodoc as document_number',
                            'mstd_tgldoc as document_date',
                            'mstd_qty as quantity',
                            'mstd_gross as amount',
                            'mstd_flagdisc1 as previous_condition',
                            'mstd_flagdisc2 as new_condition',
                            'mstd_ocost as old_cost',
                            'mstd_avgcost as new_cost',
                            'mstd_recordid as record_status',
                            'mstd_create_by as created_by',
                        ])
                        ->where('mstd_typetrn', 'Z')
                        ->whereBetween('mstd_prdcd', $pluRange)
                        ->orderByDesc('mstd_tgldoc')
                        ->limit(100)
                        ->get(),
                    'purchase-orders' => $connection->table('tbtr_po_d as detail')
                        ->leftJoin('tbtr_po_h as header', 'detail.tpod_nopo', '=', 'header.tpoh_nopo')
                        ->leftJoin('tbmaster_supplier as supplier', 'header.tpoh_kodesupplier', '=', 'supplier.sup_kodesupplier')
                        ->select([
                            'detail.tpod_tglpo as po_date',
                            'detail.tpod_nopo as po_number',
                            'detail.tpod_prdcd as plu',
                            'detail.tpod_qtypo as quantity',
                            'detail.tpod_hrgsatuan as unit_price',
                            'detail.tpod_qtypb as received_quantity',
                            'header.tpoh_jwpb as delivery_days',
                            'supplier.sup_kodesupplier as supplier_code',
                            'supplier.sup_namasupplier as supplier_name',
                        ])
                        ->whereBetween('detail.tpod_prdcd', $pluRange)
                        ->whereRaw("COALESCE(detail.tpod_recordid, '2') <> '1'")
                        ->orderByDesc('detail.tpod_tglpo')
                        ->limit(50)
                        ->get(),
                    'cashback-history' => $connection->table('tbtr_cashback_dtl as detail')
                        ->leftJoin('tbtr_cashback_hdr as header', 'detail.cbd_kodepromosi', '=', 'header.cbh_kodepromosi')
                        ->leftJoin('tbtr_cashback_alokasi as allocation', 'detail.cbd_kodepromosi', '=', 'allocation.cba_kodepromosi')
                        ->select([
                            'header.cbh_kodepromosi as promotion_code',
                            'header.cbh_namapromosi as promotion_name',
                            'header.cbh_mekanisme as mechanism',
                            'detail.cbd_minstruk as minimum_quantity',
                            'header.cbh_minrphprodukpromo as minimum_sponsor_amount',
                            'header.cbh_mintotbelanja as minimum_receipt_amount',
                            'detail.cbd_cashback as cashback',
                            'header.cbh_tglawal as starts_at',
                            'header.cbh_tglakhir as ends_at',
                            'allocation.cba_reguler as regular_member',
                            'allocation.cba_retailer as retailer_member',
                            'allocation.cba_silver as silver_member',
                            'allocation.cba_platinum as platinum_member',
                        ])
                        ->whereBetween('detail.cbd_prdcd', $pluRange)
                        ->where('header.cbh_tglawal', '>=', '2023-01-01')
                        ->orderByDesc('header.cbh_tglawal')
                        ->limit(100)
                        ->get(),
                    'gift-history' => $connection->table('tbtr_gift_dtl as detail')
                        ->leftJoin('tbtr_gift_hdr as header', 'detail.gfd_kodepromosi', '=', 'header.gfh_kodepromosi')
                        ->leftJoin('tbtr_gift_alokasi as allocation', 'detail.gfd_kodepromosi', '=', 'allocation.gfa_kodepromosi')
                        ->select([
                            'detail.gfd_kodepromosi as promotion_code',
                            'header.gfh_namapromosi as promotion_name',
                            'header.gfh_mekanisme as mechanism',
                            'detail.gfd_prdcd as plu',
                            'detail.gfd_pcs as quantity',
                            'detail.gfd_rph as amount',
                            'header.gfh_jmlhadiah as gift_quantity',
                            'header.gfh_kethadiah as gift_description',
                            'header.gfh_tglawal as starts_at',
                            'header.gfh_tglakhir as ends_at',
                            'allocation.gfa_reguler as regular_member',
                            'allocation.gfa_retailer as retailer_member',
                            'allocation.gfa_platinum as platinum_member',
                        ])
                        ->whereBetween('detail.gfd_prdcd', $pluRange)
                        ->where('header.gfh_tglawal', '>=', '2023-01-01')
                        ->orderByDesc('header.gfh_tglawal')
                        ->limit(100)
                        ->get(),
                    'restrictions' => $this->restrictions($connectionName, $pluRange),
                    'spb' => $connection->table('tbtemp_antrianspb as spb')
                        ->select([
                            'spb.spb_id as id',
                            'spb.spb_lokasiasal as source_location',
                            'spb.spb_lokasitujuan as destination_location',
                            'spb.spb_jenis as type',
                            'spb.spb_prdcd as plu',
                            'spb.spb_minus as quantity',
                            'spb.spb_recordid as record_status',
                            'spb.spb_create_dt as created_at',
                        ])
                        ->where('spb.spb_prdcd', $plu)
                        ->whereRaw("COALESCE(spb.spb_recordid, '0') IN (?, ?)", ['3', '0'])
                        ->orderByDesc('spb.spb_create_dt')
                        ->limit(100)
                        ->get(),
                    'slp' => $connection->table('tbtr_slp as slp')
                        ->select([
                            'slp.slp_id as id',
                            'slp.slp_koderak as rack',
                            'slp.slp_kodesubrak as subrack',
                            'slp.slp_tiperak as rack_type',
                            'slp.slp_shelvingrak as shelving',
                            'slp.slp_nourut as sequence',
                            'slp.slp_qtypcs as quantity_pieces',
                            'slp.slp_expdate as expires_at',
                            'slp.slp_create_dt as created_at',
                        ])
                        ->where('slp.slp_prdcd', $plu)
                        ->whereNull('slp.slp_flag')
                        ->orderByDesc('slp.slp_create_dt')
                        ->limit(100)
                        ->get(),
                    'stock-planogram' => $this->stockAndPlanogram($connectionName, $plu),
                    'sales-monthly' => $this->monthlySales($connectionName, $plu),
                    'sales' => $connection->table('tbtr_jualdetail as sales')
                        ->select([
                            'sales.trjd_transactiondate as transaction_date',
                            'sales.trjd_transactionno as transaction_number',
                            'sales.trjd_seqno as sequence',
                            'sales.trjd_transactiontype as transaction_type',
                            'sales.trjd_prdcd as plu',
                            'sales.trjd_quantity as quantity',
                            'sales.trjd_unitprice as unit_price',
                            'sales.trjd_discount as discount',
                            'sales.trjd_nominalamt as amount',
                            'sales.trjd_cus_kodemember as member_code',
                            'sales.trjd_create_by as cashier',
                            'sales.trjd_cashierstation as cashier_station',
                        ])
                        ->whereBetween('sales.trjd_prdcd', $pluRange)
                        ->where('sales.trjd_transactiondate', '>=', now()->subDays(90)->startOfDay())
                        ->orderByDesc('sales.trjd_transactiondate')
                        ->orderByDesc('sales.trjd_transactionno')
                        ->limit(100)
                        ->get(),
                    'price-margin' => $this->priceMargin($connectionName, $plu),
                    'so-ic' => $connection->table('tbtr_reset_soic as stocktake')
                        ->leftJoin('tbmaster_prodmast as product', 'stocktake.rso_prdcd', '=', 'product.prd_prdcd')
                        ->select([
                            'stocktake.rso_tglso as stocktake_date',
                            'stocktake.rso_prdcd as plu',
                            'product.prd_deskripsipanjang as description',
                            'stocktake.rso_qtyplano as planogram_quantity',
                            'stocktake.rso_qtylpp as book_stock',
                            'stocktake.rso_qtyreset as reset_quantity',
                        ])
                        ->selectRaw('stocktake.rso_avgcostreset * stocktake.rso_qtyreset AS reset_amount')
                        ->where('stocktake.rso_prdcd', $plu)
                        ->orderByDesc('stocktake.rso_tglso')
                        ->limit(100)
                        ->get(),
                    'so-daily' => $connection->table('tmp.spi1m_hasilso_harian as stocktake')
                        ->leftJoin('tbmaster_prodmast as product', 'stocktake.so_prdcd', '=', 'product.prd_prdcd')
                        ->leftJoin('tbmaster_stock as stock', function ($join): void {
                            $join->on('stocktake.so_prdcd', '=', 'stock.st_prdcd')
                                ->where('stock.st_lokasi', '=', '01');
                        })
                        ->select([
                            'stocktake.so_create_dt as stocktake_date',
                            'stocktake.so_prdcd as plu',
                            'product.prd_deskripsipanjang as description',
                            'stocktake.so_plano as planogram_quantity',
                            'stocktake.so_lpp as book_stock',
                        ])
                        ->selectRaw('(stocktake.so_plano - stocktake.so_lpp) AS reset_quantity')
                        ->selectRaw('stock.st_avgcost * (stocktake.so_plano - stocktake.so_lpp) AS reset_amount')
                        ->where('stocktake.so_prdcd', $plu)
                        ->orderByDesc('stocktake.so_create_dt')
                        ->limit(100)
                        ->get(),
                    'picking' => $connection->table('tbtr_obi_d as detail')
                        ->leftJoin('tbtr_obi_h as header', function ($join): void {
                            $join->on('detail.obi_tgltrans', '=', 'header.obi_tgltrans')
                                ->on('detail.obi_notrans', '=', 'header.obi_notrans');
                        })
                        ->leftJoin('tbmaster_customer as customer', function ($join): void {
                            $join->on('header.obi_kdmember', '=', 'customer.cus_kodemember')
                                ->where('customer.cus_kodeigr', '=', '1M');
                        })
                        ->select([
                            'header.obi_nopb as pb_number',
                            'header.obi_tglpb as pb_date',
                            'header.obi_kdmember as member_code',
                            'customer.cus_namamember as member_name',
                            'detail.obi_prdcd as plu',
                            'detail.obi_qtyorder as ordered_quantity',
                            'detail.obi_qtyrealisasi as realized_quantity',
                            'detail.obi_picker as picker',
                            'detail.obi_pick_dt as picked_at',
                            'detail.obi_scan_dt as scanned_at',
                        ])
                        ->selectRaw("CASE
                            WHEN header.obi_recid LIKE 'B%' THEN 'BATAL'
                            WHEN header.obi_recid IS NULL THEN 'BLM_SEND'
                            WHEN header.obi_recid = '1' THEN 'PICKING'
                            WHEN header.obi_recid = '2' THEN 'PACKING'
                            WHEN header.obi_recid = '3' THEN 'SIAP_DSP'
                            WHEN header.obi_recid = '5' THEN 'DSP'
                            WHEN header.obi_recid = '6' THEN 'STRUK'
                        END AS pb_status")
                        ->whereBetween('detail.obi_prdcd', $pluRange)
                        ->orderByDesc('header.obi_tglpb')
                        ->limit(100)
                        ->get(),
                    'dsi' => $this->daysSalesInventory($connectionName, $plu),
                    'promotion-prices' => $connection->table('tbtr_promomd as promotion')
                        ->leftJoin('tbmaster_prodmast as product', 'promotion.prmd_prdcd', '=', 'product.prd_prdcd')
                        ->select([
                            'promotion.prmd_prdcd as plu',
                            'product.prd_deskripsipanjang as description',
                            'product.prd_avgcost as average_cost',
                            'product.prd_hrgjual as regular_price',
                            'promotion.prmd_jenisdisc as discount_type',
                            'promotion.prmd_hrgjual as promo_price',
                            'promotion.prmd_hrgjualbaru as new_promo_price',
                            'promotion.prmd_tglawal as starts_at',
                            'promotion.prmd_tglakhir as ends_at',
                            'promotion.prmd_status as status',
                        ])
                        ->whereBetween('promotion.prmd_prdcd', $pluRange)
                        ->orderByDesc('promotion.prmd_tglawal')
                        ->limit(100)
                        ->get(),
                    default => throw new \InvalidArgumentException("Unknown PLU panel [{$panel}]."),
                };
            },
        );
    }

    /** @param list<string> $transactionTypes
     * @return Collection<int, stdClass>
     */
    /**
     * @param  array{0: string, 1: string}  $pluRange
     * @param  list<string>  $transactionTypes
     * @return Collection<int, stdClass>
     */
    private function stockMovements(?string $connectionName, array $pluRange, array $transactionTypes): Collection
    {
        return DB::connection($connectionName)
            ->table('tbtr_mstran_d as movement')
            ->leftJoin('tbmaster_supplier as supplier', 'movement.mstd_kodesupplier', '=', 'supplier.sup_kodesupplier')
            ->select([
                'movement.mstd_typetrn as transaction_type',
                'movement.mstd_kodesupplier as supplier_code',
                'supplier.sup_namasupplier as supplier_name',
                'movement.mstd_qty as quantity',
                'movement.mstd_qtybonus1 as bonus_quantity_one',
                'movement.mstd_qtybonus2 as bonus_quantity_two',
                'movement.mstd_nodoc as document_number',
                'movement.mstd_nopo as po_number',
                'movement.mstd_noref3 as reference_number',
                'movement.mstd_tgldoc as document_date',
                'movement.mstd_create_dt as created_at',
            ])
            ->selectRaw('(movement.mstd_gross - movement.mstd_discrph) / NULLIF(movement.mstd_qty, 0) AS last_cost')
            ->selectRaw('movement.mstd_avgcost / NULLIF(movement.mstd_frac, 0) AS average_cost')
            ->whereBetween('movement.mstd_prdcd', $pluRange)
            ->whereIn('movement.mstd_typetrn', $transactionTypes)
            ->whereNull('movement.mstd_recordid')
            ->orderByDesc('movement.mstd_create_dt')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    /** @param array{0: string, 1: string} $pluRange
     * @return Collection<int, stdClass>
     */
    private function restrictions(?string $connectionName, array $pluRange): Collection
    {
        $connection = DB::connection($connectionName);
        $memberTypes = [
            'REGBIRUPLUS' => 'biru_plus',
            'REGBIRU' => 'biru',
            'FREEPASS' => 'free_pass',
            'RETMERAH' => 'retailer',
            'SILVER' => 'silver',
            'GOLD1' => 'gold_one',
            'GOLD2' => 'gold_two',
            'GOLD3' => 'gold_three',
            'PLATINUM' => 'platinum',
        ];
        $limitColumns = [
            'biru' => 'mtr_qtyregulerbiru',
            'biru_plus' => 'mtr_qtyregulerbiruplus',
            'free_pass' => 'mtr_qtyfreepass',
            'retailer' => 'mtr_qtyretailermerah',
            'silver' => 'mtr_qtysilver',
            'gold_one' => 'mtr_qtygold1',
            'gold_two' => 'mtr_qtygold2',
            'gold_three' => 'mtr_qtygold3',
            'platinum' => 'mtr_qtyplatinum',
        ];
        $limitQuery = $connection->table('tbtabel_maxtransaksi')
            ->selectRaw("'Pembatasan qty' AS restriction_type, 'Pcs' AS unit");

        foreach ($limitColumns as $label => $column) {
            $limitQuery->addSelect("{$column} as {$label}");
        }

        $allocationQuery = $connection->table('tbtr_promomd_alokasi as allocation')
            ->join('tbtr_promomd as promotion', 'allocation.alk_prdcd', '=', 'promotion.prmd_prdcd')
            ->selectRaw("'Pembatasan promo' AS restriction_type, 'Ctn' AS unit");

        foreach ($memberTypes as $memberType => $label) {
            $allocationQuery->selectRaw(
                'SUM(CASE WHEN allocation.alk_member = ? THEN allocation.alk_qtyalokasi ELSE 0 END) AS '.$label,
                [$memberType],
            );
        }

        $allocationQuery
            ->whereBetween('allocation.alk_prdcd', $pluRange)
            ->where('promotion.prmd_tglakhir', '>=', now()->toDateString())
            ->groupBy('restriction_type', 'unit');

        return $limitQuery
            ->whereBetween('mtr_prdcd', $pluRange)
            ->unionAll($allocationQuery)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    private function stockAndPlanogram(?string $connectionName, string $plu): Collection
    {
        $connection = DB::connection($connectionName);
        $stock = $connection->table('tbmaster_stock')
            ->select('st_prdcd')
            ->selectRaw('SUM(COALESCE(st_saldoakhir, 0)) AS book_stock')
            ->selectRaw('MAX(COALESCE(st_avgcost, 0)) AS average_cost')
            ->where('st_lokasi', '01')
            ->where('st_prdcd', $plu)
            ->groupBy('st_prdcd');
        $planogram = $connection->table('tbmaster_lokasi')
            ->select('lks_prdcd')
            ->selectRaw('SUM(COALESCE(lks_qty, 0)) AS planogram_quantity')
            ->where('lks_prdcd', $plu)
            ->groupBy('lks_prdcd');

        return $connection->table('tbmaster_prodmast as product')
            ->leftJoin('tbmaster_prodcrm as crm', 'product.prd_prdcd', '=', 'crm.prc_pluigr')
            ->leftJoinSub($stock, 'stock', 'product.prd_prdcd', '=', 'stock.st_prdcd')
            ->leftJoinSub($planogram, 'planogram', 'product.prd_prdcd', '=', 'planogram.lks_prdcd')
            ->select([
                'product.prd_prdcd as plu',
                'product.prd_deskripsipanjang as description',
                'product.prd_unit as unit',
                'product.prd_frac as fraction',
                'product.prd_kodetag as tag',
                'crm.prc_pluidm as idm_plu',
            ])
            ->selectRaw('COALESCE(stock.average_cost, 0) AS average_cost')
            ->selectRaw('COALESCE(stock.book_stock, 0) AS book_stock')
            ->selectRaw('COALESCE(planogram.planogram_quantity, 0) AS planogram_quantity')
            ->selectRaw('COALESCE(planogram.planogram_quantity, 0) - COALESCE(stock.book_stock, 0) AS quantity_difference')
            ->where('product.prd_prdcd', $plu)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    private function monthlySales(?string $connectionName, string $plu): Collection
    {
        $query = DB::connection($connectionName)
            ->table('tbtr_rekapsalesbulanan as sales')
            ->leftJoin('tbmaster_prodmast as product', 'sales.rsl_prdcd', '=', 'product.prd_prdcd')
            ->select([
                'sales.rsl_prdcd as plu',
                'product.prd_deskripsipanjang as description',
                'sales.rsl_kodesupplier as supplier_code',
                'sales.rsl_group as member_group',
            ]);

        foreach (range(1, 12) as $month) {
            $monthNumber = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $query->addSelect("sales.rsl_qty_{$monthNumber} as quantity_{$monthNumber}");
            $query->addSelect("sales.rsl_rph_{$monthNumber} as sales_amount_{$monthNumber}");
            $query->addSelect("sales.rsl_hpp_{$monthNumber} as cost_amount_{$monthNumber}");
            $query->selectRaw(
                "COALESCE(sales.rsl_rph_{$monthNumber}, 0) - COALESCE(sales.rsl_hpp_{$monthNumber}, 0) AS margin_amount_{$monthNumber}",
            );
        }

        return $query->where('sales.rsl_prdcd', $plu)
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    private function priceMargin(?string $connectionName, string $plu): Collection
    {
        $pluRange = $this->pluFamilyBounds($plu);
        $query = <<<'SQL'
            WITH products AS (
                SELECT
                    product.prd_prdcd AS plu,
                    product.prd_deskripsipanjang AS description,
                    product.prd_unit AS unit,
                    product.prd_frac AS fraction,
                    product.prd_minjual AS minimum_sale,
                    product.prd_avgcost AS average_cost,
                    product.prd_lastcost AS last_cost,
                    product.prd_hrgjual AS regular_price,
                    CASE
                        WHEN product.prd_unit = 'KG' AND product.prd_minjual = 1
                            THEN product.prd_minjual * 1000
                        ELSE product.prd_minjual
                    END AS normalized_minimum_sale
                FROM tbmaster_prodmast AS product
                WHERE product.prd_prdcd BETWEEN ? AND ?
            ),
            member_promotions AS (
                SELECT
                    promotion.prmd_prdcd AS plu,
                    promotion.prmd_hrgjual AS promo_price,
                    MAX(promotion.prmd_tglawal) AS promo_starts_at,
                    MAX(promotion.prmd_tglakhir) AS promo_ends_at
                FROM tbtr_promomd AS promotion
                LEFT JOIN tbtr_promomd_alokasi AS allocation
                    ON SUBSTRING(promotion.prmd_prdcd, 1, 6) || '0' = allocation.alk_prdcd
                WHERE promotion.prmd_tglawal < CURRENT_DATE + INTERVAL '1 day'
                    AND promotion.prmd_tglakhir >= CURRENT_DATE
                    AND promotion.prmd_prdcd BETWEEN ? AND ?
                    AND CASE
                        WHEN allocation.alk_member = 'PLATINUM' THEN 'PLATINUM'
                        WHEN allocation.alk_member IN ('REGBIRUPLUS', 'REGBIRU') THEN 'BIRU'
                        ELSE 'MERAH'
                    END = 'MERAH'
                GROUP BY promotion.prmd_prdcd, promotion.prmd_hrgjual
            ),
            priced_products AS (
                SELECT
                    product.*,
                    promotion.promo_price,
                    promotion.promo_starts_at,
                    promotion.promo_ends_at,
                    COALESCE(NULLIF(promotion.promo_price, 0), product.regular_price) AS price_used,
                    CASE
                        WHEN product.unit LIKE '%RCG%' THEN product.normalized_minimum_sale
                        WHEN product.unit = 'KG' THEN product.normalized_minimum_sale
                        ELSE product.fraction * product.normalized_minimum_sale
                    END AS promotion_quantity
                FROM products AS product
                LEFT JOIN member_promotions AS promotion ON product.plu = promotion.plu
            ),
            eligible_cashbacks AS (
                SELECT DISTINCT
                    detail.cbd_prdcd AS promotion_plu,
                    header.cbh_jenispromosi AS promotion_type,
                    detail.cbd_cashback AS item_cashback,
                    header.cbh_cashback AS receipt_cashback,
                    detail.cbd_minstruk AS minimum_quantity,
                    CASE
                        WHEN header.cbh_minrphprodukpromo < header.cbh_mintotbelanja
                            THEN header.cbh_mintotbelanja
                        WHEN header.cbh_minrphprodukpromo > 0
                            THEN header.cbh_minrphprodukpromo
                        ELSE header.cbh_mintotbelanja
                    END AS minimum_receipt_amount,
                    CASE
                        WHEN detail.cbd_maxstruk IN (0, 999999) THEN 999999999
                        ELSE detail.cbd_maxstruk
                    END AS maximum_quantity,
                    CASE
                        WHEN header.cbh_maxstrkperhari = 999999 THEN 999999999
                        ELSE header.cbh_maxstrkperhari
                    END AS maximum_receipt_amount
                FROM tbtr_cashback_hdr AS header
                JOIN tbtr_cashback_dtl AS detail
                    ON header.cbh_kodepromosi = detail.cbd_kodepromosi
                JOIN tbtr_cashback_alokasi AS allocation
                    ON header.cbh_kodepromosi = allocation.cba_kodepromosi
                WHERE header.cbh_tglakhir >= CURRENT_DATE
                    AND header.cbh_tglawal < CURRENT_DATE + INTERVAL '1 day'
                    AND detail.cbd_prdcd = ?
                    AND (
                        COALESCE(allocation.cba_retailer, '0') = '1'
                        OR COALESCE(allocation.cba_silver, '0') = '1'
                        OR COALESCE(allocation.cba_gold1, '0') = '1'
                    )
                    AND COALESCE(detail.cbd_recordid, '2') <> '1'
                    AND header.cbh_jenispromosi <> '4'
                    AND (header.cbh_flagspi = 'Y' OR header.cbh_flagigr = 'Y')
                    AND COALESCE(detail.cbd_redeempoint, '0') = '0'
            ),
            cashback_by_product AS (
                SELECT
                    product.plu,
                    SUM(
                        CASE
                            WHEN cashback.minimum_receipt_amount <> 0 THEN
                                FLOOR(
                                    CASE
                                        WHEN (
                                            CASE
                                                WHEN product.plu LIKE '%0' THEN product.price_used
                                                ELSE product.price_used * product.promotion_quantity
                                            END
                                        ) > cashback.maximum_receipt_amount
                                            THEN cashback.maximum_receipt_amount
                                        ELSE
                                            CASE
                                                WHEN product.plu LIKE '%0' THEN product.price_used
                                                ELSE product.price_used * product.promotion_quantity
                                            END
                                    END / NULLIF(cashback.minimum_receipt_amount, 0)
                                )
                            ELSE 0
                        END * COALESCE(cashback.receipt_cashback, 0)
                        +
                        CASE
                            WHEN COALESCE(cashback.minimum_quantity, 0) = 0 THEN 0
                            WHEN product.unit = 'RCG' THEN FLOOR(
                                (product.promotion_quantity * product.fraction) / cashback.minimum_quantity
                            )
                            WHEN product.unit = 'KG' THEN FLOOR(
                                product.promotion_quantity / cashback.minimum_quantity
                            )
                            WHEN product.promotion_quantity > cashback.maximum_quantity THEN FLOOR(
                                cashback.maximum_quantity / cashback.minimum_quantity
                            )
                            ELSE FLOOR(product.promotion_quantity / cashback.minimum_quantity)
                        END * CASE
                            WHEN cashback.promotion_type = '5' THEN 0
                            ELSE COALESCE(cashback.item_cashback, 0)
                        END
                    ) AS cashback
                FROM priced_products AS product
                JOIN eligible_cashbacks AS cashback
                    ON SUBSTRING(product.plu, 1, 6) || '0' = cashback.promotion_plu
                GROUP BY product.plu
            )
            SELECT
                product.plu,
                product.description,
                product.unit,
                product.fraction,
                product.minimum_sale,
                product.regular_price,
                product.promo_price,
                product.price_used AS price_per_unit,
                product.minimum_sale * product.price_used AS minimum_sale_total,
                COALESCE(cashback.cashback, 0) AS cashback_per_minimum_sale,
                product.minimum_sale * product.price_used - COALESCE(cashback.cashback, 0) AS net_price,
                product.average_cost,
                product.last_cost,
                (
                    (product.minimum_sale * product.price_used - COALESCE(cashback.cashback, 0))::numeric
                    / NULLIF(product.minimum_sale, 0)::numeric
                ) - product.average_cost AS margin_per_unit,
                product.promo_starts_at,
                product.promo_ends_at
            FROM priced_products AS product
            LEFT JOIN cashback_by_product AS cashback ON product.plu = cashback.plu
            ORDER BY product.plu
            SQL;

        return collect(DB::connection($connectionName)->select($query, [
            $pluRange[0],
            $pluRange[1],
            $pluRange[0],
            $pluRange[1],
            $plu,
        ]));
    }

    /** @return Collection<int, stdClass> */
    private function daysSalesInventory(?string $connectionName, string $plu): Collection
    {
        $connection = DB::connection($connectionName);
        $pluRange = $this->pluFamilyBounds($plu);
        $sales = $connection->table('tbtr_jualdetail as detail')
            ->join('tbmaster_prodmast as sales_product', 'detail.trjd_prdcd', '=', 'sales_product.prd_prdcd')
            ->selectRaw("SUBSTRING(detail.trjd_prdcd, 1, 6) || '0' AS plu")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN sales_product.prd_unit = 'KG' THEN
                            CASE WHEN detail.trjd_transactiontype = 'S' THEN detail.trjd_quantity ELSE -detail.trjd_quantity END / 1000
                        ELSE
                            CASE WHEN detail.trjd_transactiontype = 'S' THEN detail.trjd_quantity ELSE -detail.trjd_quantity END * sales_product.prd_frac
                    END
                ) AS sold_quantity
            ")
            ->selectRaw('COUNT(DISTINCT detail.trjd_transactiondate::date) AS sales_days')
            ->whereBetween('detail.trjd_prdcd', $pluRange)
            ->where('detail.trjd_transactiondate', '>=', now()->subMonths(3)->startOfMonth())
            ->where('detail.trjd_transactiondate', '<', now()->startOfMonth())
            ->groupByRaw("SUBSTRING(detail.trjd_prdcd, 1, 6) || '0'");
        $openPurchaseOrders = $connection->table('tbtr_po_d as detail')
            ->join('tbtr_po_h as header', 'detail.tpod_nopo', '=', 'header.tpoh_nopo')
            ->select('detail.tpod_prdcd')
            ->selectRaw('SUM(detail.tpod_qtypo) AS open_quantity')
            ->where('detail.tpod_prdcd', $plu)
            ->where(function ($query): void {
                $query->where('detail.tpod_qtypb', 0)
                    ->orWhereNull('detail.tpod_qtypb');
            })
            ->whereNull('header.tpoh_recordid')
            ->whereRaw("header.tpoh_tglpo::date + (COALESCE(header.tpoh_jwpb, 0)::integer * INTERVAL '1 day') >= CURRENT_DATE")
            ->groupBy('detail.tpod_prdcd');

        return $connection->table('tbmaster_prodmast as product')
            ->leftJoin('tbmaster_stock as stock', function ($join): void {
                $join->on('product.prd_prdcd', '=', 'stock.st_prdcd')
                    ->where('stock.st_lokasi', '=', '01');
            })
            ->leftJoinSub($sales, 'sales', 'product.prd_prdcd', '=', 'sales.plu')
            ->leftJoinSub($openPurchaseOrders, 'purchase_orders', 'product.prd_prdcd', '=', 'purchase_orders.tpod_prdcd')
            ->select([
                'product.prd_prdcd as plu',
                'product.prd_deskripsipanjang as description',
                'product.prd_kodetag as tag',
                'product.prd_unit as unit',
                'product.prd_frac as fraction',
                'stock.st_saldoakhir as stock',
            ])
            ->selectRaw('COALESCE(sales.sold_quantity, 0) AS sold_quantity_three_months')
            ->selectRaw('COALESCE(sales.sales_days, 0) AS sales_days')
            ->selectRaw('COALESCE(purchase_orders.open_quantity, 0) AS open_po_quantity')
            ->selectRaw('COALESCE(sales.sold_quantity, 0) / NULLIF(sales.sales_days, 0) AS average_daily_quantity')
            ->selectRaw('stock.st_saldoakhir / NULLIF(COALESCE(sales.sold_quantity, 0) / NULLIF(sales.sales_days, 0), 0) AS days_sales_inventory')
            ->selectRaw('(stock.st_saldoakhir + COALESCE(purchase_orders.open_quantity, 0)) / NULLIF(COALESCE(sales.sold_quantity, 0) / NULLIF(sales.sales_days, 0), 0) AS days_sales_inventory_including_po')
            ->where('product.prd_prdcd', $plu)
            ->limit(1)
            ->get();
    }

    private function resolvePlu(string $input, ?string $connectionName): ?string
    {
        $value = trim($input);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value) && strlen($value) <= 7) {
            return substr(str_pad($value, 7, '0', STR_PAD_LEFT), 0, 6).'0';
        }

        $barcodeProduct = DB::connection($connectionName)
            ->table('tbmaster_barcode')
            ->where('brc_barcode', $value)
            ->value('brc_prdcd');

        return is_string($barcodeProduct)
            ? substr($barcodeProduct, 0, 6).'0'
            : null;
    }

    /** @return array{0: string, 1: string} */
    private function pluFamilyBounds(string $plu): array
    {
        $prefix = substr($plu, 0, 6);

        return [$prefix.'0', $prefix.'9'];
    }

    private function product(string $plu, ?string $connectionName): ?stdClass
    {
        $connection = DB::connection($connectionName);
        $stock = $connection->table('tbmaster_stock')
            ->select('st_prdcd')
            ->selectRaw('MAX(COALESCE(st_saldoakhir, 0)) AS stock')
            ->where('st_lokasi', '01')
            ->where('st_prdcd', $plu)
            ->groupBy('st_prdcd');
        $supplier = $connection->table('tbmaster_hargabeli as purchase')
            ->leftJoin('tbmaster_supplier as vendor', 'purchase.hgb_kodesupplier', '=', 'vendor.sup_kodesupplier')
            ->select('purchase.hgb_prdcd')
            ->selectRaw('MAX(vendor.sup_kodesupplier) AS supplier_code')
            ->selectRaw('MAX(vendor.sup_namasupplier) AS supplier_name')
            ->where('purchase.hgb_tipe', '2')
            ->where('purchase.hgb_prdcd', $plu)
            ->groupBy('purchase.hgb_prdcd');
        $openOrders = $this->openOrders($plu, $connectionName);
        $iccStock = $connection->table('tbmaster_stock_spi_icc')
            ->select('sts_prdcd')
            ->selectRaw('SUM(COALESCE(sts_qty, 0)) AS quantity')
            ->where('sts_prdcd', $plu)
            ->groupBy('sts_prdcd');

        return $connection->table($this->getTable().' as product')
            ->leftJoin('tbmaster_divisi as division', 'product.prd_kodedivisi', '=', 'division.div_kodedivisi')
            ->leftJoin('tbmaster_departement as department', 'product.prd_kodedepartement', '=', 'department.dep_kodedepartement')
            ->leftJoin('tbmaster_kategori as category', function ($join): void {
                $join->on('product.prd_kodedepartement', '=', 'category.kat_kodedepartement')
                    ->on('product.prd_kodekategoribarang', '=', 'category.kat_kodekategori');
            })
            ->leftJoinSub($stock, 'stock', 'product.prd_prdcd', '=', 'stock.st_prdcd')
            ->leftJoinSub($supplier, 'supplier', 'product.prd_prdcd', '=', 'supplier.hgb_prdcd')
            ->leftJoinSub($openOrders, 'open_orders', 'product.prd_prdcd', '=', 'open_orders.plu')
            ->leftJoinSub($iccStock, 'icc_stock', 'product.prd_prdcd', '=', 'icc_stock.sts_prdcd')
            ->select([
                'product.prd_prdcd as plu',
                'product.prd_plumcg as plu_mcg',
                'product.prd_deskripsipendek as short_description',
                'product.prd_deskripsipanjang as description',
                'product.prd_unit as unit',
                'product.prd_frac as fraction',
                'product.prd_kodetag as tag',
                'product.prd_kodedivisi as division_code',
                'division.div_namadivisi as division_name',
                'product.prd_kodedepartement as department_code',
                'department.dep_namadepartement as department_name',
                'product.prd_kodekategoribarang as category_code',
                'category.kat_namakategori as category_name',
                'product.prd_kodesupplier as product_supplier_code',
                'supplier.supplier_code',
                'supplier.supplier_name',
                'product.prd_avgcost as average_cost',
                'product.prd_lastcost as last_cost',
                'product.prd_minjual as minimum_sale',
                'product.prd_hrgjual as selling_price',
                'product.prd_hrgjual2 as selling_price_2',
                'product.prd_hrgjual3 as selling_price_3',
                'product.prd_tglhrgjual as selling_price_updated_at',
                'product.prd_flagigr as igr_flag',
                'product.prd_flagbkp1 as tax_flag',
                'product.prd_flagbkp2 as tax_category',
                'product.prd_recordid as record_status',
                'product.prd_flag_aktivasi as activation_status',
                'product.prd_modify_by as modified_by',
                'product.prd_modify_dt as modified_at',
            ])
            ->selectRaw('COALESCE(stock.stock, 0) AS stock')
            ->selectRaw('COALESCE(open_orders.order_quantity, 0) AS open_order_quantity')
            ->selectRaw('COALESCE(open_orders.order_count, 0) AS open_order_count')
            ->selectRaw('COALESCE(icc_stock.quantity, 0) AS icc_quantity')
            ->selectRaw('COALESCE(stock.stock, 0) - COALESCE(open_orders.order_quantity, 0) - COALESCE(icc_stock.quantity, 0) AS real_stock')
            ->where('product.prd_prdcd', $plu)
            ->first();
    }

    private function openOrders(string $plu, ?string $connectionName): Builder
    {
        $connection = DB::connection($connectionName);

        return $connection->table('tbtr_obi_d as detail')
            ->leftJoin('tbtr_obi_h as header', function ($join): void {
                $join->on('detail.obi_tgltrans', '=', 'header.obi_tgltrans')
                    ->on('detail.obi_notrans', '=', 'header.obi_notrans');
            })
            ->selectRaw("SUBSTRING(detail.obi_prdcd, 1, 6) || '0' AS plu")
            ->selectRaw('SUM(COALESCE(detail.obi_qtyorder, 0)) AS order_quantity')
            ->selectRaw('COUNT(DISTINCT header.obi_nopb) AS order_count')
            ->whereBetween('detail.obi_prdcd', $this->pluFamilyBounds($plu))
            ->whereRaw("COALESCE(header.obi_recid, '1') IN ('1', '2', '3')")
            ->groupByRaw("SUBSTRING(detail.obi_prdcd, 1, 6) || '0'");
    }
}
