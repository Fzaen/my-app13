<?php
$sql = "SELECT 
    CAB,
    'SPI BANJARMASIN 1' AS NAMACABANG,
    to_char(tglbpb::date, 'DD-MON-YY') AS PERIODE,
    kodesup,
    SUPPLIER,
    -- dsup_kodesupplier,
    dsup_namavendor,
    dsup_picvendor,
    dsup_kontakvendor,
    dsup_driver_picsupplier,
    dsup_driver_kontaksupplier,
    dsup_picsales,
    dsup_kontaksales,
    dsup_atasan_picsales,
    dsup_atasan_kontaksales,
--    DIV,
--    DEPT,
--    KATB,
    plu,
    desk,
    bpb_qty,
    TPOD_HRGSATUAN * po_qty AS po_rph
FROM (
    SELECT 
        MSTD_KODEIGR CAB,
        PRD_KODEDIVISI AS DIV,
        PRD_KODEDEPARTEMENT AS DEPT,
        PRD_KODEKATEGORIBARANG AS KATB,
        prd_plumcg AS plumcg,
        mstd_prdcd AS plu,
        prd_deskripsipanjang AS desk,
        mstd_unit AS unit,
        mstd_frac AS frac,
        prd_kodetag AS tag,
        mstd_discrph bpb_diskon,
        MSTD_HRGSATUAN,
        TPOD_HRGSATUAN,
        mstd_nopo AS nopo,
        TO_CHAR(mstd_tgldoc, 'dd-MON-yy') AS tglbpb,
        mstd_nodoc AS nobpb,
        (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) AS bpb_qty,
        (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) / mstd_frac AS bpb_ctn,
        mstd_gross AS bpp_rpexl,
        (mstd_gross - mstd_discrph) + mstd_ppnrph AS bpb_rpincl,
        TO_CHAR(tpod_tglpo, 'dd-MON-yy') AS tglpo,
        tpod_qtypo AS po_qty,
        tpod_qtypo / mstd_frac AS po_ctn,
        tpod_gross AS po_rpexl,
        ((tpod_gross) + tpod_ppn) AS po_rpincl,
        tpod_rphttldisc po_diskon,
        tpod_ppn PO_PPN,
        mstd_ppnrph bpb_ppn,
        mstd_kodesupplier AS kodesup,
        SUP_NAMASUPPLIER AS SUPPLIER
    FROM tbtr_mstran_d
    LEFT JOIN tbmaster_prodmast ON mstd_prdcd = prd_prdcd
    LEFT JOIN tbtr_po_d ON mstd_nopo = tpod_nopo AND TPOD_PRDCD = PRD_PRDCD
    LEFT JOIN TBMASTER_SUPPLIER ON MSTD_KODESUPPLIER = SUP_KODESUPPLIER
    WHERE mstd_typetrn = 'B'
      AND mstd_tgldoc::date 
      --  BETWEEN '2026-08-01' AND '2026-08-22'
    BETWEEN '{$ptglstart}' AND '{$ptglend}'
      AND mstd_recordid IS NULL
) AS a
LEFT JOIN 
(SELECT 
    dsup_kodesupplier,
    STRING_AGG(DISTINCT dsup_namavendor, ' / ') AS dsup_namavendor,
    STRING_AGG(DISTINCT dsup_picvendor, ' / ') AS dsup_picvendor,
    STRING_AGG(DISTINCT dsup_kontakvendor, ' / ') AS dsup_kontakvendor,
    STRING_AGG(DISTINCT dsup_driver_picsupplier, ' / ') AS dsup_driver_picsupplier,
    STRING_AGG(DISTINCT dsup_driver_kontaksupplier, ' / ') AS dsup_driver_kontaksupplier,
    STRING_AGG(DISTINCT dsup_picsales, ' / ') AS dsup_picsales,
    STRING_AGG(DISTINCT dsup_kontaksales, ' / ') AS dsup_kontaksales,
    STRING_AGG(DISTINCT dsup_atasan_picsales, ' / ') AS dsup_atasan_picsales,
    STRING_AGG(DISTINCT dsup_atasan_kontaksales, ' / ') AS dsup_atasan_kontaksales,
    MAX(dsup_create_at) AS dsup_create_at,
    MAX(dsup_update_at) AS dsup_update_at
FROM tmp.data_supplier
GROUP BY dsup_kodesupplier
ORDER BY dsup_kodesupplier) as pic
ON DSUP_KODESUPPLIER=kodesup
-- LEFT JOIN tmp.WSN_FLAG ON a.plu = PLUF
ORDER BY 
    tglbpb DESC, 
    plu";
