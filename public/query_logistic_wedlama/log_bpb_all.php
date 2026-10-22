<?php
$sql = "SELECT 
    CAB,
    to_char(to_date(tglbpb, 'DD-MON-YY'), 'MONTH-YY') AS PERIODE,
    NULL AS LOKASI_TOKO,
    lokasi_display,
    DIV,
    DEPT,
    KATB,
    plumcg,
    plu,
    NULL AS mcg,
    desk,
    frac,
    tag,
    NULL AS tagidm,
    NULL AS tagomi,
    nopo,
    po_qty,
    TPOD_HRGSATUAN * po_qty AS po_rph,
    po_ppn,
    po_diskon,
    nobpb,
    bpb_qty,
    bpb_qty * TPOD_HRGSATUAN AS bpp_rph,
    bpb_ppn,
    bpb_diskon,
    round(bpb_qty / po_qty * 100.0) AS slqty,
    round((bpb_qty * TPOD_HRGSATUAN) / (TPOD_HRGSATUAN * po_qty) * 100.0) AS slrph,
    to_char(to_date(tglbpb, 'DD-MON-YY'), 'DD-MON-YY') AS tglbpb,
    SUPPLIER,
    kodesup,
    'PO_REALISASI' AS KET
FROM (
    SELECT 
        MSTD_KODEIGR CAB,
        PRD_KODEDIVISI AS DIV,
        LOKASI_DISPLAY,
        LOKASI_GUDANG,
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
    LEFT JOIN tmp.wsn_lokasi ON plulok = mstd_prdcd
    LEFT JOIN tbmaster_prodmast ON mstd_prdcd = prd_prdcd
    LEFT JOIN tbtr_po_d ON mstd_nopo = tpod_nopo AND TPOD_PRDCD = PRD_PRDCD
    LEFT JOIN TBMASTER_SUPPLIER ON MSTD_KODESUPPLIER = SUP_KODESUPPLIER
    WHERE mstd_typetrn = 'B'
      AND mstd_tgldoc::date 
      -- BETWEEN '2026-04-17' AND '2026-04-17'
     BETWEEN '{$ptglstart}' AND '{$ptglend}'
      AND mstd_recordid IS NULL
) AS a
LEFT JOIN (
    SELECT 
        tpod_prdcd AS OUT_PRD,
        SUM(tpod_qtypo) AS out_qty
    FROM tbtr_po_d
    LEFT JOIN tbtr_po_h ON tpod_nopo = tpoh_nopo
    WHERE coalesce(tpod_recordid, '0') IN ('0', '2', '*')
      AND TPOD_QTYPB IS NULL
      AND (tpoh_tglpo + (interval '1 days' * tpoh_jwpb))::date > current_date
    GROUP BY tpod_prdcd
) AS b ON a.plu = b.out_prd
LEFT JOIN tmp.WSN_FLAG ON a.plu = PLUF
ORDER BY 
    tglbpb DESC, 
    plu";
