<?php
$sql = "SELECT
prd_kodeigr                             cabang_igr,   
 to_char(tpoh_tglpo, 'MON')               periode, 
  lks_koderak
    || '.'
    || lks_kodesubrak
    || '.'
    || lks_tiperak
    || '.'
    || lks_shelvingrak
    || '.'
    || lks_nourut AS lokasi_display_dpd,
    prd_kodedivisi                            div,
    prd_kodedepartement                      dept,
    prd_kodekategoribarang                  kat,
    prd_prdcd                                 plu,
    prd_plumcg                              plu_mcg,
    prd_deskripsipanjang                    desk,
    prd_frac                                  frac,
    prd_kodetag                               tag,
    tpod_nopo                                 nopo,
    tpod_qtypo                                qtypo,
    tpoh_tglpo                                tglpo,
    tpod_hrgsatuan * tpod_qtypo               rp_po,
    mstd_nodoc                                no_bpb,
    mstd_qty                                  qty_bpb,
    ( mstd_hrgsatuan / mstd_frac ) * mstd_qty rp_bpb,
    ( mstd_qty / tpod_qtypo ) * 100           AS sl_qty,
    (( mstd_hrgsatuan / mstd_frac ) * mstd_qty) / (tpod_hrgsatuan * tpod_qtypo ) * 100 AS SL_RP,
    to_char(mstd_tgldoc, 'yyyy-mm-dd hh:mm:ss')       tgl_bpb,
    sup_namasupplier                          nama_suppl,
    tpoh_kodesupplier                         kd_suppl    
FROM
    tbtr_po_d
    LEFT JOIN tbmaster_prodmast ON tpod_prdcd = prd_prdcd
    LEFT JOIN tbmaster_lokasi   lks ON prd_prdcd = lks_prdcd  -- JOIN baru untuk tbmaster_lokasi
    JOIN (
        SELECT
            tpoh_tglpo,
            tpoh_nopo,
            tpoh_kodesupplier,
            sup_namasupplier
        FROM
            tbtr_po_h
            LEFT JOIN tbmaster_supplier ON tpoh_kodesupplier = sup_kodesupplier            
    ) sub1 ON tpod_nopo = tpoh_nopo
    LEFT JOIN (
        SELECT
            *
        FROM
            tbtr_mstran_d
        WHERE
            mstd_typetrn = 'B'
    ) sub2 ON tpod_prdcd = mstd_prdcd
              AND tpod_nopo = mstd_nopo
WHERE
    date(tpod_tglpo) BETWEEN '{$ptglstart}' AND '{$ptglend}'
ORDER BY
    1,
    2,
    3,
    4,
    to_char(tpod_tglpo,'YYYYMMDD')";
