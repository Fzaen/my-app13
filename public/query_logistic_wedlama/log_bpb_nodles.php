<?php
$sql = "SELECT 
            DIV,
            DEPT,
            KATB,
            plu,
            desk,
            unit,
            frac,
            kodesup,
            MSTD_HRGSATUAN,
            SUPPLIER,
            TO_CHAR(TO_DATE(tglpo, 'DD-MON-YY'), 'DD-MON-YY') AS tglpo,
            nopo,
            po_qty,
            po_ctn,
            po_rpexl,
            po_rpincl,
            TO_CHAR(TO_DATE(tglbpb, 'DD-MON-YY'), 'DD-MON-YY') AS tglbpb,
            nobpb,
            bpb_qty,
            bpb_ctn,
            bpp_rpexl,
            bpb_rpincl,
            'PO_REALISASI' AS KET
        FROM
            (
                SELECT 
                    PRD_KODEDIVISI AS DIV,
                    PRD_KODEDEPARTEMENT AS DEPT,
                    PRD_KODEKATEGORIBARANG AS KATB,
                    mstd_prdcd AS plu,
                    prd_deskripsipanjang AS desk,
                    mstd_unit AS unit,
                    mstd_frac AS frac,
                    prd_kodetag AS tag,
                    MSTD_HRGSATUAN,
                    TPOD_HRGSATUAN,
                    (
                        CASE
                            WHEN prd_PRDCD IN (SELECT prd_prdcd FROM tbmaster_prodmast WHERE prd_flagidm = 'Y')
                                THEN 'IDM+ONLY'
                            WHEN prd_PRDCD IN (
                                SELECT prd_prdcd
                                FROM tbmaster_prodmast
                                WHERE prd_flagidm <> 'Y'
                                  AND prd_prdcd NOT IN (SELECT prc_pluigr FROM tbmaster_prodcrm)
                            )
                                THEN 'IGR+ONLY'
                            ELSE 'IGR+IDM'
                        END
                    ) AS FlagS,
                    TO_CHAR(mstd_tgldoc, 'dd-MON-yy') AS tglbpb,
                    mstd_nodoc AS nobpb,
                    (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) AS bpb_qty,
                    (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) / mstd_frac AS bpb_ctn,
                    mstd_gross AS bpp_rpexl,
                    (mstd_gross - mstd_discrph) + mstd_ppnrph AS bpb_rpincl,
                    TO_CHAR(tpod_tglpo, 'dd-MON-yy') AS tglpo,
                    mstd_nopo AS nopo,
                    tpod_qtypo AS po_qty,
                    tpod_qtypo / mstd_frac AS po_ctn,
                    tpod_gross AS po_rpexl,
                    ((tpod_gross) + tpod_ppn) AS po_rpincl,
                    mstd_kodesupplier AS kodesup,
                    SUP_NAMASUPPLIER AS SUPPLIER
                FROM tbtr_mstran_d
                LEFT JOIN tbmaster_prodmast ON mstd_prdcd = prd_prdcd
                LEFT JOIN tbtr_po_d ON mstd_nopo = tpod_nopo AND TPOD_PRDCD = PRD_PRDCD
                LEFT JOIN TBMASTER_SUPPLIER ON MSTD_KODESUPPLIER = SUP_KODESUPPLIER
                WHERE mstd_typetrn = 'B'
                  AND prd_prdcd = tpod_prdcd
                  AND mstd_tgldoc::date BETWEEN '{$ptglstart}' AND '{$ptglend}'
                  AND mstd_recordid IS NULL
                  AND PRD_KODEDEPARTEMENT = '08'
                  AND PRD_KODEKATEGORIBARANG IN ('01','02')
                  --AND SUP_KODESUPPLIER IN ('I0620','I0621','I0622','S1471')                     
            ) AS a
        LEFT JOIN
            (
                SELECT 
                    tpod_prdcd AS OUT_PRD,
                    SUM(tpod_qtypo) AS out_qty
                FROM tbtr_po_d
                LEFT JOIN tbtr_po_h ON tpod_nopo = tpoh_nopo
                WHERE COALESCE(tpod_recordid, '0') IN ('0', '2', '*')
                  AND TPOD_QTYPB IS NULL
                  AND (tpoh_tglpo + (INTERVAL '1 days' * tpoh_jwpb))::date > CURRENT_DATE
                GROUP BY tpod_prdcd
            ) AS b ON plu = out_prd
        LEFT JOIN tmp.WSN_FLAG ON PLU = PLUF                      
        ORDER BY 
            tglbpb DESC,
            plu";
