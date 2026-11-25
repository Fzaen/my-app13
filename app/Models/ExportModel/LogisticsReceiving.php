<?php

namespace App\Models\ExportModel;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogisticsReceiving
{
    /**
     * @return Collection<int, \stdClass>
     */
    public static function suppliers(): Collection
    {
        $sql = <<<'SQL'
            SELECT
                orders.nobtc,
                suppliers.id_sup,
                suppliers.sup_namasupplier,
                CASE
                    WHEN batches.sts = 0 THEN 'SELESAI'
                    WHEN orders.selesai > 0 THEN 'IMPORT DCP'
                    WHEN orders.sudah > 0 AND orders.belum = 0 AND orders.input = 0 THEN 'CLOSE'
                    WHEN orders.belum > 0 AND orders.input > 0 THEN 'SCAN'
                    WHEN orders.sudah = 0 AND orders.input = 0 THEN 'BLM INPUT'
                    ELSE 'SEBAGIAN'
                END AS cheker,
                CASE
                    WHEN contact.dsup_driver_kontaksupplier IS NOT NULL
                        AND contact.dsup_kontakvendor IS NOT NULL THEN 'DRIVER & VENDOR'
                    WHEN contact.dsup_driver_kontaksupplier IS NOT NULL THEN 'DRIVER'
                    WHEN contact.dsup_kontakvendor IS NOT NULL THEN 'VENDOR'
                    ELSE 'BELUM ADA'
                END AS pic
            FROM (
                SELECT
                    nobtc,
                    SUM(CASE WHEN idsts IS NULL THEN 1 ELSE 0 END) AS belum,
                    SUM(CASE WHEN idsts = '1' THEN 1 ELSE 0 END) AS input,
                    SUM(CASE WHEN idsts = '2' THEN 1 ELSE 0 END) AS sudah,
                    SUM(CASE WHEN idsts = '3' THEN 1 ELSE 0 END) AS selesai
                FROM dcp_get_po
                GROUP BY nobtc
            ) AS orders
            LEFT JOIN (
                SELECT DISTINCT
                    batch.no_btc AS antrian,
                    batch.id_sup,
                    supplier.sup_namasupplier
                FROM dcp_batch_po AS batch
                LEFT JOIN tbmaster_supplier AS supplier
                    ON supplier.sup_kodesupplier = batch.id_sup
            ) AS suppliers
                ON orders.nobtc = suppliers.antrian
            LEFT JOIN (
                SELECT
                    batch.no_btc AS antrian,
                    COUNT(batch.po_btc) - SUM(CASE WHEN batch.id_sts = '7' THEN 1 ELSE 0 END) AS sts
                FROM dcp_batch_po AS batch
                GROUP BY batch.no_btc
            ) AS batches
                ON batches.antrian = orders.nobtc
            LEFT JOIN (
                SELECT
                    dsup_kodesupplier,
                    STRING_AGG(DISTINCT dsup_kontakvendor, ' / ') AS dsup_kontakvendor,
                    STRING_AGG(DISTINCT dsup_driver_kontaksupplier, ' / ') AS dsup_driver_kontaksupplier
                FROM tmp.data_supplier
                GROUP BY dsup_kodesupplier
            ) AS contact
                ON contact.dsup_kodesupplier = suppliers.id_sup
            ORDER BY orders.nobtc
            SQL;

        return collect(DB::connection()->select($sql));
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function purchaseOrders(): Collection
    {
        $sql = <<<'SQL'
            SELECT
                batch.no_btc AS antrian,
                batch.no_urt AS urut,
                batch.po_btc AS po,
                TO_CHAR(batch.po_tgl, 'DD-MON-YY') AS po_tgl,
                TO_CHAR(batch.po_exp, 'DD-MON-YY') AS po_exp,
                batch.rp_dpo,
                batch.no_bpb,
                batch.rp_bpb,
                batch.id_sup,
                supplier.sup_namasupplier,
                CASE
                    WHEN batch.id_sts = '1' THEN 'PROSES'
                    WHEN batch.id_sts = '3' THEN 'CLOSE'
                    WHEN batch.id_sts = '7' THEN 'SELESAI'
                    ELSE batch.id_sts
                END AS recid
            FROM dcp_batch_po AS batch
            LEFT JOIN tbmaster_supplier AS supplier
                ON supplier.sup_kodesupplier = batch.id_sup
            ORDER BY batch.no_btc, batch.no_urt
            SQL;

        return collect(DB::connection()->select($sql));
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function items(): Collection
    {
        $sql = <<<'SQL'
            SELECT DISTINCT
                po.recid,
                CASE
                    WHEN po.recid IS NULL THEN 'PROSES'
                    WHEN po.recid = '2' THEN 'SELESAI'
                    ELSE po.recid
                END AS ket,
                batch.id_sup,
                po.prdcd,
                product.prd_deskripsipanjang,
                ROUND(COALESCE(product.prd_avgcost, 0) / NULLIF(product.prd_frac, 0)) AS acost,
                ROUND(COALESCE(po.hrgbeli, 0) - COALESCE(order_detail.tpod_rphdisc2 / NULLIF(order_detail.tpod_qtypo, 0), 0)) AS hrg_beli,
                order_detail.tpod_qtypo,
                po.qtypo AS qty_inputpo,
                po.qtybpb,
                po.qtybpb / NULLIF(product.prd_frac, 0) AS bpbctn,
                po.qtybpb / NULLIF(order_detail.tpod_qtypo, 0) * 100 AS persen,
                po.gross AS dpp,
                batch.rp_bpb,
                transaction_detail.mstd_gross,
                transaction_detail.mstd_ppnrph,
                po.bonus1,
                po.bonus2,
                po.nopo,
                batch.no_bpb,
                batch.no_btc AS antrian,
                batch.no_urt AS urutpo,
                CASE WHEN COALESCE(receipt_count.penerimaan, 0) <= 1 THEN 'BARU' ELSE 'LAMA' END AS produk,
                barkos.bksp_range_dsi AS barkos
            FROM dcp_real_po AS po
            LEFT JOIN (
                SELECT bksp_plu, bksp_range_dsi
                FROM tmp.rekap_barkos_plu
                WHERE bksp_tgl::date = CURRENT_DATE
            ) AS barkos
                ON barkos.bksp_plu = po.prdcd
            LEFT JOIN dcp_batch_po AS batch
                ON batch.po_btc = po.nopo
            LEFT JOIN tbtr_po_d AS order_detail
                ON order_detail.tpod_prdcd = po.prdcd
                AND order_detail.tpod_nopo = po.nopo
            LEFT JOIN tbmaster_prodmast AS product
                ON product.prd_prdcd = po.prdcd
            LEFT JOIN tbtr_mstran_d AS transaction_detail
                ON transaction_detail.mstd_prdcd = po.prdcd
                AND transaction_detail.mstd_nodoc = batch.no_bpb
            LEFT JOIN (
                SELECT mstd_prdcd, COUNT(mstd_nodoc) AS penerimaan
                FROM tbtr_mstran_d
                WHERE mstd_typetrn = 'B'
                    AND mstd_recordid IS NULL
                    AND mstd_prdcd IN (SELECT prdcd FROM dcp_real_po)
                GROUP BY mstd_prdcd
            ) AS receipt_count
                ON receipt_count.mstd_prdcd = po.prdcd
            WHERE po.qtypo <> 0
            ORDER BY produk, antrian, urutpo, no_bpb
            SQL;

        return collect(DB::connection()->select($sql));
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function supplierReturns(string $supplier): Collection
    {
        $sql = <<<'SQL'
            SELECT DISTINCT
                stock.st_prdcd AS plu,
                product.prd_deskripsipanjang AS desk,
                stock.st_avgcost AS cost,
                stock.st_saldoakhir AS stock,
                product.prd_perlakuanbarang AS status,
                CASE WHEN btb.btb_invno IS NOT NULL THEN 'BISA' ELSE 'BELUM' END AS ket,
                supplier.kodesupplier,
                supplier.namasupplier
            FROM tbmaster_stock AS stock
            LEFT JOIN (
                SELECT latest_btb.*
                FROM tbtr_mstran_btb AS latest_btb
                LEFT JOIN (
                    SELECT btb_prdcd AS plub, MAX(btb_tgldoc) AS tgl
                    FROM tbtr_mstran_btb
                    WHERE btb_kodesupplier <> 'I0637'
                        AND btb_invno IS NOT NULL
                    GROUP BY btb_prdcd
                ) AS latest_invoice
                    ON latest_invoice.plub = latest_btb.btb_prdcd
                    AND latest_invoice.tgl = latest_btb.btb_tgldoc
                WHERE latest_invoice.plub IS NOT NULL
                    AND latest_invoice.tgl IS NOT NULL
            ) AS btb
                ON stock.st_prdcd = btb.btb_prdcd
            LEFT JOIN tbmaster_prodmast AS product
                ON product.prd_prdcd = stock.st_prdcd
            LEFT JOIN tmp.wsn_supplier AS supplier
                ON supplier.plusupplier = stock.st_prdcd
            WHERE stock.st_lokasi = '02'
                AND supplier.kodesupplier = ?
                AND stock.st_saldoakhir > 0
            ORDER BY plu
            SQL;

        return collect(DB::connection()->select($sql, [$supplier]));
    }
}
