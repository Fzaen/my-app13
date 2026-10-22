<?php
$sql =
  "SELECT DISTINCT 
    prd_kodedivisi AS div, 
    prd_kodedepartement AS dept, 
    --  dep_namadepartement,
    prd_kodekategoribarang AS katb, 
    --  kat_namakategori,
    prd_prdcd AS plu, 
    prd_plumcg AS plumd, 
    prd_deskripsipanjang AS desk, 
    spi, 
    prd_kodetag AS TAGIGR, 
    prc_kodetag AS TAGIDM, 
    prd_perlakuanbarang AS status, 
    prd_unit AS unit, 
    prd_frac AS frac, 
    MINJUAL_MBT, 
    prd_lastcost AS LCOST_0, 
    LCOST_1, 
    LCOST_2, 
    LCOST_3, 
    prd_avgcost AS ACOST_0, 
    ACOST_1, 
    ACOST_2, 
    ACOST_3, 
    prd_hrgjual AS HRGJUAL_0, 
    HRGJUAL_1, 
    HRGJUAL_2, 
    HRGJUAL_3, 
    COALESCE(STOCK, 0)-COALESCE(qtybpbicc, 0)-COALESCE(pbtrxout, 0) STOCK_REAL,
    SALES, 
    ROUND((NULLIF(STOCK, 0) + NULLIF(POOUT, 0)) / (NULLIF(SALES, 0) / HARI), 0) AS DSIspi, 
    CASE 
        WHEN COALESCE(PRD_FLAGBKP1, 'T') = 'Y' THEN ROUND((prd_hrgjual - (prd_avgcost * 1.11)) / prd_hrgjual * 100, 2) 
        ELSE ROUND((prd_hrgjual - prd_avgcost) / prd_hrgjual * 100, 2) 
    END AS MARGIN_0, 
    CASE 
        WHEN COALESCE(PRD_FLAGBKP1, 'T') = 'Y' THEN ROUND((HRGJUAL_1 - (ACOST_1 * 1.11)) / HRGJUAL_1 * 100, 2) 
        ELSE ROUND((HRGJUAL_1 - ACOST_1) / HRGJUAL_1 * 100, 2) 
    END AS MARGIN_1, 
    CASE 
        WHEN COALESCE(PRD_FLAGBKP1, 'T') = 'Y' THEN ROUND((HRGJUAL_2 - (ACOST_2 * 1.11)) / HRGJUAL_2 * 100, 2) 
        ELSE ROUND((HRGJUAL_2 - ACOST_2) / HRGJUAL_2 * 100, 2) 
    END AS MARGIN_2, 
    CASE 
        WHEN COALESCE(PRD_FLAGBKP1, 'T') = 'Y' THEN ROUND((HRGJUAL_3 - (ACOST_3 * 1.11)) / HRGJUAL_3 * 100, 2) 
        ELSE ROUND((HRGJUAL_3 - ACOST_3) / HRGJUAL_3 * 100, 2) 
    END AS MARGIN_3, 
    pkm_leadtime AS LT, 
    pkm_koefisien AS KF, 
    PKM_PKM AS PKM, 
    pkm_pkmt AS PKMT, 
    Pkmp_Qtyminor AS Mplus, 
    pkm_mpkm AS MPKM, 
    pkm_mindisplay AS MINDIS, 
    prd_minorder AS MINOR, 
    mpt_maxqty AS MAXPALET, 
    POOUT, 
    QTYBTB, 
    TO_CHAR(TGLBTB, 'DD-MON-YY') AS TGLBTB_TERAKHIR, 
    FLAG, 
    FLAG2, 
    KOSUP, 
    NASUP, 
    KPH, 
    Qty_Avgmm, 
    QTY_AVGALL, 
    QTY_AVGIDM, 
    QTY_AVGMM_perharibelanja, 
    lokasi_toko, 
    lokasi_gudang, 
    Pkmp_Mplusi AS MPLUSI, 
    PKM_KOEFISIEN, 
    Maxplanotoko, 
    LKS_JENIS19 AS JENIS_ITEM, 
    PRD_FLAGBKP1, 
    PRD_FLAGBKP2 || 
        CASE 
            WHEN KFP_FLAGBKP2 = 'Y' THEN '-PPN' 
            WHEN KFP_FLAGBKP2 = 'C' THEN '-CUKAI' 
            WHEN KFP_FLAGBKP2 = 'N' THEN '-TIDAK' 
            ELSE '-' || KFP_STATUSPAJAK 
        END || 
        CASE 
            WHEN KFP_KATEGORI IS NULL THEN '' 
            ELSE '-' || KFP_KATEGORI 
        END AS PRD_FLAGBKP2, 
    PRD_TGLDISCONTINUE, 
    Jml_Avgmbrmb, 
    Jml_Avgmbrmm, 
    Hrg_Omi, 
    QTY_AVGMB, 
    obi_intransit, 
    PRD_DIMENSILEBAR AS Lebar, 
    PRD_DIMENSIPANJANG AS Panjang, 
    PRD_DIMENSITINGGI AS Tinggi, 
    POANTRI, 
    qtypb_proses, 
    statuspb,    
    COALESCE(qtybpbicc, 0) qtybpbicc,
    COALESCE(pbtrxout, 0) pbtrxout,    
    COALESCE(STOCK, 0) AS STOCK_ALL,
    btbpertama,
    hargabelictn,
    discount1,
    discount2,
    discount2a,
    discount2b,
    discount3,
    discount4,
    discount5,
    discount6,
    to_char(prd_create_dt,'dd-mon-yy') create_dt,
	to_char(prd_tgldaftar,'dd-mon-yy') tgldaftar,
	to_char(prd_tglaktif,'dd-mon-yy') tglaktif
FROM tbmaster_prodmast 
LEFT JOIN tbmaster_kodefp ON (prd_FLAGBKP2 = KFP_FLAGBKP2) 
LEFT JOIN tbmaster_prodcrm ON (prd_prdcd = prc_pluigr) 
LEFT JOIN tbmaster_kkpkm ON (prd_prdcd = pkm_prdcd) 
LEFT JOIN tbmaster_pkmplus ON (prd_prdcd = pkmp_prdcd) 
LEFT JOIN tbmaster_maxpalet ON (prd_prdcd = mpt_prdcd) 
LEFT JOIN tbmaster_departement ON (prd_kodedepartement = dep_kodedepartement) 
LEFT JOIN tbmaster_kategori ON (prd_kodedepartement || prd_kodekategoribarang = kat_kodedepartement || kat_kodekategori) 
LEFT JOIN (select substr(obi_prdcd,1,6)||0 plupbouts, sum(obi_qtyorder) pbtrxout
                from (
                select obi_prdcd,obi_nopb, obi_qtyorder from tbtr_obi_d 
                left join (
                select obi_recid recid, obi_nopb,
                obi_tgltrans tgltrans,	obi_notrans notrans
                from tbtr_obi_h) as obih on obi_tgltrans=tgltrans and obi_notrans=notrans
                where coalesce(recid,'1') in ('1','2','3')) as pb
                group by plupbouts) AS klikorder on plupbouts=prd_prdcd
LEFT JOIN (select sts_prdcd plubpbicc, sts_qty qtybpbicc from tbmaster_stock_spi_icc) AS BPBHJKICC on prd_prdcd=plubpbicc
-- Subquery PBA (Proses PB)
LEFT JOIN (
    SELECT 
        plupb, 
        STRING_AGG(DISTINCT status, ', ' ORDER BY status) AS statuspb, 
        SUM(qtypb) AS qtypb_proses 
    FROM (
        SELECT 
            CASE 
                WHEN COALESCE(PBD_RECORDID, '0') = '1' AND COALESCE(PBD_NOPO, '0') = '0' AND pbh_TGLTRANSFER IS NOT NULL THEN 'PB TDK REALISASI' 
                WHEN COALESCE(PBD_RECORDID, '0') = '1' AND COALESCE(PBD_NOPO, '0') <> '0' AND pbh_TGLTRANSFER IS NOT NULL THEN 'TERBIT PO' 
                WHEN pbh_TGLTRANSFER IS NULL THEN 'BELUM TRANSFER KE MD' 
                ELSE 'BELUM PROSES MD' 
            END AS status, 
            pbd_prdcd AS plupb, 
            PBD_QTYPB AS qtypb 
        FROM TBTR_PB_D 
        LEFT JOIN TBTR_PB_H ON PBD_NOPB = PBH_NOPB 
        WHERE PBD_CREATE_DT::date BETWEEN CURRENT_DATE - INTERVAL '14 days' AND CURRENT_DATE
    ) AS pba 
    WHERE status IN ('BELUM PROSES MD', 'BELUM TRANSFER KE MD') 
    GROUP BY plupb
) AS pba ON prd_prdcd = plupb 

-- Subquery PO Antri
LEFT JOIN (
    SELECT 
        TPOD_PRDCD AS PLUANTRI, 
        SUM(tpod_qtypo) AS POANTRI 
    FROM TBTR_PO_D 
    WHERE TPOD_NOPO IN (
        SELECT PO_BTC FROM DCP_BATCH_PO WHERE NO_BPB IS NULL
    ) 
    GROUP BY TPOD_PRDCD
) AS poantri_data ON PRD_PRDCD = PLUANTRI 

-- Subquery A (LCOST 1)
LEFT JOIN (
    SELECT DISTINCT (SUBSTRING(prd_prdcd, 1, 6) || 0) AS plu1, 
        prd_lastcost AS LCOST_1, 
        prd_avgcost AS ACOST_1, 
        prd_hrgjual AS HRGJUAL_1 
    FROM tbmaster_prodmast 
    WHERE prd_prdcd LIKE '%1'
) AS a ON prd_prdcd = plu1 

-- Subquery B (Supplier)
LEFT JOIN (
    SELECT DISTINCT (hgb_prdcd) AS plu2, 
        hgb_kodesupplier AS KOSUP, 
        sup_namasupplier AS NASUP,
        Hgb_Nilaidpp AS Hrg_Omi ,
        hgb_hrgbeli*hgb_isisatuanbeli HargaBeliCtn,
        hgb_rphdisc01 Discount1,
        hgb_rphdisc02 Discount2,
        hgb_rphdisc02ii Discount2a,
        hgb_rphdisc02iii Discount2b,
        hgb_rphdisc03 Discount3,
        hgb_rphdisc04 Discount4,
        hgb_rphdisc05 Discount5,
        hgb_rphdisc06 Discount6  
    FROM tbmaster_hargabeli 
    LEFT JOIN tbmaster_supplier ON (hgb_kodesupplier = sup_kodesupplier) 
    WHERE hgb_tipe = '2'
) AS b ON prd_prdcd = plu2 

-- Subquery C (PO Out)
LEFT JOIN (
    SELECT DISTINCT (tpod_prdcd) AS pluout, 
        SUM(tpod_qtypo) AS poout 
    FROM tbtr_po_d, tbtr_po_h 
    WHERE (tpod_qtypb = '0' OR tpod_qtypb IS NULL) 
        AND tpoh_tglpo + (tpoh_jwpb || ' days')::interval >= CURRENT_DATE 
        AND tpod_nopo = tpoh_nopo 
        AND tpoh_recordid IS NULL 
    GROUP BY (tpod_prdcd)
) AS c ON prd_prdcd = pluout 

-- Subquery D (LCOST 2)
LEFT JOIN (
    SELECT DISTINCT (SUBSTRING(prd_prdcd, 1, 6) || 0) AS plu3, 
        prd_lastcost AS LCOST_2, 
        prd_avgcost AS ACOST_2, 
        prd_hrgjual AS HRGJUAL_2, 
        prd_minjual AS MINJUAL_MBT 
    FROM tbmaster_prodmast 
    WHERE prd_prdcd LIKE '%2'
) AS d ON prd_prdcd = plu3 

-- Subquery E (LCOST 3)
LEFT JOIN (
    SELECT DISTINCT (SUBSTRING(prd_prdcd, 1, 6) || 0) AS plu7, 
        prd_lastcost AS LCOST_3, 
        prd_avgcost AS ACOST_3, 
        prd_hrgjual AS HRGJUAL_3, 
        prd_minjual AS MINJUAL_3 
    FROM tbmaster_prodmast 
    WHERE prd_prdcd LIKE '%3'
) AS e ON prd_prdcd = plu7 

-- Subquery F (Stock)
LEFT JOIN (
    SELECT DISTINCT 
        st_prdcd AS plustock, 
        st_saldoakhir AS stock, 
        st_sales AS sales, 
        ROUND(
            CASE 
                WHEN st_sales != 0 THEN (((st_saldoawal + st_saldoakhir) / 2) / st_sales) * EXTRACT(DAY FROM CURRENT_DATE) 
                ELSE 0 
            END, 0
        ) AS DSI, 
        EXTRACT(DAY FROM CURRENT_DATE) AS hari 
    FROM TBMASTER_STOCK 
    WHERE ST_LOKASI = '01'
) AS f ON prd_prdcd = plustock 

-- Subquery H (BTB Terakhir)
LEFT JOIN (
    SELECT DISTINCT PLU AS PLUM, SUM(QTY) AS QTYBTB, SUM(rpbtb) AS rpbtb, TGLBTB 
    FROM (
        SELECT MSTD_PRDCD AS PLU, MAX(MSTD_TGLDOC) AS TGLBTB 
        FROM TBTR_MSTRAN_D 
        WHERE mstd_typetrn IN ('B') 
        GROUP BY MSTD_PRDCD
    ) AS ac 
    LEFT JOIN (
        SELECT MSTD_PRDCD, (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) AS qty, 
            (mstd_gross - mstd_discrph) + mstd_ppnrph AS rpbtb, (MSTD_TGLDOC) AS TGLBTB1 
        FROM TBTR_MSTRAN_D 
        WHERE mstd_typetrn IN ('B')
    ) AS g ON PLU = MSTD_PRDCD AND TGLBTB = TGLBTB1 
    GROUP BY PLU, TGLBTB
) AS h ON prd_prdcd = PLUM 

-- Subquery I (KPH)
LEFT JOIN (
    SELECT prd_prdcd AS plu11, SUM(CEIL((ksl_mean * 3) / prd_frac) * prd_frac) AS kph 
    FROM tbmaster_kph 
    LEFT JOIN tbmaster_prodmast ON prdcd = prd_plumcg 
    WHERE pid = '092023' 
    GROUP BY prd_prdcd
) AS i ON prd_prdcd = plu11 

-- Subquery J (Avg MM Per Hari Belanja)
LEFT JOIN (
    SELECT plu1 AS plu17, ROUND(SUM(qty_igr / hari), 0) AS QTY_AVGMM_perharibelanja 
    FROM (
        SELECT DISTINCT (SUBSTRING(trjd_prdcd, 1, 6) || 0) AS plu1, 
            COUNT(DISTINCT ((trjd_transactiondate)::date)) AS Hari, 
            SUM(CASE 
                    WHEN TRJD_TRANSACTIONTYPE = 'S' THEN (CASE WHEN PRD_UNIT = 'KG' THEN (TRJD_QUANTITY / 1000) ELSE (TRJD_QUANTITY * PRD_FRAC) END) 
                    ELSE ((CASE WHEN PRD_unit = 'KG' THEN (TRJD_QUANTITY / 1000) ELSE (TRJD_QUANTITY * PRD_FRAC) END) * -1) 
                END) AS QTY_IGR 
        FROM tbtr_jualdetail 
        LEFT JOIN tbmaster_prodmast ON trjd_prdcd = prd_prdcd 
        WHERE (trjd_transactiondate)::date >= DATE('01-' || TO_CHAR(CURRENT_DATE - INTERVAL '3 MONTH', 'MON-YY')) 
            AND (trjd_transactiondate)::date < DATE('01-' || TO_CHAR(CURRENT_DATE, 'MON-YY')) 
            AND trjd_cus_kodemember IN (SELECT cus_kodemember FROM tbmaster_customer WHERE cus_flagmemberkhusus = 'Y') 
        GROUP BY (SUBSTRING(trjd_prdcd, 1, 6) || 0)
    ) AS i 
    GROUP BY plu1
) AS j ON prd_prdcd = plu17 

-- Subquery L (Avg ALL)
LEFT JOIN (
    SELECT plu1 AS plu13, ROUND(SUM(qty_igr / 3), 0) AS QTY_AVGALL 
    FROM (
        SELECT DISTINCT (SUBSTRING(trjd_prdcd, 1, 6) || 0) AS plu1, 
            COUNT(DISTINCT ((trjd_transactiondate)::date)) AS Hari, 
            SUM(CASE 
                    WHEN TRJD_TRANSACTIONTYPE = 'S' THEN (CASE WHEN PRD_UNIT = 'KG' THEN (TRJD_QUANTITY / 1000) ELSE (TRJD_QUANTITY * PRD_FRAC) END) 
                    ELSE ((CASE WHEN PRD_unit = 'KG' THEN (TRJD_QUANTITY / 1000) ELSE (TRJD_QUANTITY * PRD_FRAC) END) * -1) 
                END) AS QTY_IGR 
        FROM tbtr_jualdetail 
        LEFT JOIN tbmaster_prodmast ON trjd_prdcd = prd_prdcd 
        WHERE (trjd_transactiondate)::date >= DATE('01-' || TO_CHAR(CURRENT_DATE - INTERVAL '3 MONTH', 'MON-YY')) 
            AND (trjd_transactiondate)::date < DATE('01-' || TO_CHAR(CURRENT_DATE, 'MON-YY')) 
        GROUP BY (SUBSTRING(trjd_prdcd, 1, 6) || 0)
    ) AS k 
    GROUP BY plu1
) AS l ON prd_prdcd = plu13 

-- Subquery N (Avg IDM)
LEFT JOIN (
    SELECT plu1 AS plu16, ROUND(SUM(qty_idm / 3), 0) AS QTY_AVGIDM 
    FROM (
        SELECT DISTINCT SUBSTRING(sls_prdcd, 1, 6) || '0' AS plu1, 
            COUNT(DISTINCT (sls_periode)::date) AS Hari, 
            SUM(sls_qtyidm) AS qty_idm 
        FROM tbtr_sumsales 
        LEFT JOIN tbmaster_prodmast ON sls_prdcd = prd_prdcd 
        WHERE (sls_periode)::date >= TO_DATE('01-' || TO_CHAR(CURRENT_DATE - INTERVAL '3 MONTH', 'MON-YY'), 'DD-MON-YY') 
            AND (sls_periode)::date < TO_DATE('01-' || TO_CHAR(CURRENT_DATE, 'MON-YY'), 'DD-MON-YY') 
        GROUP BY SUBSTRING(sls_prdcd, 1, 6) || '0'
    ) AS m 
    GROUP BY plu1
) AS n ON prd_prdcd = plu16 

-- Subquery O (Lokasi Toko)
LEFT JOIN (
    SELECT LKS_PRDCD AS PLU14, 
        (LKS_KODERAK || '.' || LKS_KODESUBRAK || '.' || LKS_TIPERAK || '.' || LKS_SHELVINGRAK || '.' || LKS_NOURUT) AS LOKASI_TOKO, 
        LKS_MAXPLANO AS MAXPLANOTOKO 
    FROM tbmaster_lokasi 
    WHERE (lks_koderak LIKE 'R%' OR lks_koderak LIKE 'O%') 
        AND (lks_tiperak LIKE 'B%' OR lks_tiperak LIKE 'I%' OR lks_tiperak LIKE 'N%')
) AS o ON prd_prdcd = plu14 

-- Subquery P (Lokasi Gudang)
LEFT JOIN (
    SELECT lks_prdcd AS plu15, 
        (lks_koderak || '.' || lks_kodesubrak || '.' || lks_tiperak || '.' || lks_shelvingrak || '.' || lks_nourut) AS Lokasi_gudang 
    FROM tbmaster_lokasi 
    WHERE lks_koderak LIKE 'D%' 
        AND (LKS_TIPERAK LIKE 'B%' OR LKS_TIPERAK LIKE 'I%' OR LKS_TIPERAK LIKE 'N%')
) AS p ON PRD_PRDCD = PLU15 

-- Subquery Q (Last PO)
LEFT JOIN (
    SELECT plua, tglpo, tpod_qtypo, tpod_qtypb 
    FROM (
        SELECT (tpod_prdcd) AS plua, MAX(tpod_tglpo) AS tglpo FROM TBTR_PO_D GROUP BY tpod_prdcd
    ) AS ae 
    LEFT JOIN (
        SELECT tpod_prdcd, tpod_tglpo, tpod_qtypo, tpod_qtypb FROM TBTR_PO_D
    ) AS af ON tpod_prdcd = plua AND tpod_tglpo = tglpo
) AS q ON plua = prd_prdcd 

-- Subquery R (Service Level)
LEFT JOIN (
    SELECT SLV_PRDCD AS PLUSL, SLV_SERVICELEVEL_QTY AS SLQTY 
    FROM TBTR_SERVICELEVEL 
    WHERE SLV_PERIODE = '202309'
) AS r ON PLUSL = PRD_PRDCD 

-- Subquery S (Flag Detail)
LEFT JOIN (
    SELECT prd_prdcd AS PLUF, FLAG 
    FROM (
        SELECT prd_prdcd, 
            CASE 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' AND PRD_FLAGOMI = 'Y' AND PRD_FLAGOBI = 'Y' AND PRD_FLAGNAS = 'Y' THEN 'IGR IDM OMI OBI NAS' 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' AND PRD_FLAGOMI = 'Y' AND PRD_FLAGOBI = 'Y' THEN 'IGR IDM OMI OBI' 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' AND PRD_FLAGOBI = 'Y' THEN 'IGR IDM OBI' 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' AND PRD_FLAGOMI = 'Y' THEN 'IGR IDM OMI' 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' THEN 'IGR IDM' 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGOBI = 'Y' THEN 'IGR OBI' 
                WHEN PRD_FLAGIGR = 'Y' THEN 'IGR ONLY' 
                WHEN PRD_FLAGIDM = 'Y' THEN 'IDM ONLY' 
                WHEN PRD_FLAGOMI = 'Y' THEN 'OMI ONLY' 
                WHEN PRD_FLAGOBI = 'Y' THEN 'OBI ONLY' 
                WHEN PRD_FLAGNAS = 'Y' THEN 'NAS NOT IGR' 
                ELSE 'ZZ' 
            END AS FLAG 
        FROM Tbmaster_Prodmast
    ) AS ag
) AS s ON Pluf = Prd_Prdcd 

-- Subquery T (Flag2 Detail)
LEFT JOIN (
    SELECT prd_prdcd AS PLU18, FLAG2 
    FROM (
        SELECT prd_prdcd, 
            CASE 
                WHEN PRD_FLAGIGR = 'Y' AND PRD_FLAGIDM = 'Y' THEN 'IGR+IDM' 
                WHEN PRD_FLAGIGR = 'Y' THEN 'IGR ONLY' 
                WHEN PRD_FLAGIDM = 'Y' THEN 'IDM ONLY' 
                ELSE 'LAIN-LAIN' 
            END AS FLAG2 
        FROM Tbmaster_Prodmast
    ) AS ah
) AS t ON Plu18 = Prd_Prdcd 

-- Subquery U (Sales Biru/Member)
LEFT JOIN (
    SELECT PLUBIRU, jml_MEMBERBIRU, ROUND(JML_QTYBIRU / 3, 0) AS QTY_AVGMB, 
        ROUND(jml_MEMBERBIRU / 3, 0) AS JML_AVGMBRMB, ROUND(JML_RPHBIRU / 3, 0) AS JML_AVGRPHMB 
    FROM (
        SELECT RSL_PRDCD AS PLUBIRU, RSL_JMLMEMBER_07 + RSL_JMLMEMBER_08 + RSL_JMLMEMBER_09 AS jml_MEMBERBIRU, 
            RSL_QTY_07 + RSL_QTY_08 + RSL_QTY_09 AS JML_QTYBIRU, RSL_RPH_07 + RSL_RPH_08 + RSL_RPH_09 AS JML_RPHBIRU 
        FROM tbtr_rekapsalesbulanan 
        WHERE RSL_GROUP = '01'
    ) AS ai
) AS u ON PRD_PRDCD = PLUBIRU 
-- Subquery V (Sales Merah/Member)
LEFT JOIN (
    SELECT PLUMERAH, jml_MEMBERMERAH, ROUND(JML_QTYMERAH / 3, 0) AS QTY_AVGMM, 
        ROUND(jml_MEMBERMERAH / 3, 0) AS JML_AVGMBRMM, ROUND(JML_RPHMERAH / 3, 0) AS JML_AVGRPHMM 
    FROM (
        SELECT RSL_PRDCD AS PLUMERAH, RSL_JMLMEMBER_07 + RSL_JMLMEMBER_08 + RSL_JMLMEMBER_09 AS jml_MEMBERMERAH, 
            RSL_QTY_07 + RSL_QTY_08 + RSL_QTY_09 AS JML_QTYMERAH, RSL_RPH_07 + RSL_RPH_08 + RSL_RPH_09 AS JML_RPHMERAH 
        FROM tbtr_rekapsalesbulanan 
        WHERE RSL_GROUP = '03'
    ) AS aj
) AS v ON PRD_PRDCD = PLUMERAH
-- Subquery W (Sales Member IDM)
LEFT JOIN (
    SELECT PLUMEMBERIDM, jml_MEMBERIDM, ROUND(JML_QTYIDM / 3, 0) AS QTY_AVGIDM1, 
        ROUND(jml_MEMBERIDM / 3, 0) AS JML_AVGMBRIDM1, ROUND(JML_RPHIDM / 3, 0) AS JML_AVGRPHIDM1 
    FROM (
        SELECT RSL_PRDCD AS PLUMEMBERIDM, RSL_JMLMEMBER_07 + RSL_JMLMEMBER_08 + RSL_JMLMEMBER_09 AS jml_MEMBERIDM, 
            RSL_QTY_07 + RSL_QTY_08 + RSL_QTY_09 AS JML_QTYIDM, RSL_RPH_07 + RSL_RPH_08 + RSL_RPH_09 AS JML_RPHIDM 
        FROM tbtr_rekapsalesbulanan 
        WHERE Rsl_Group = '04'
    ) AS ak
) AS w ON Prd_Prdcd = Plumemberidm 
-- Subquery Z (Jenis Lokasi)
LEFT JOIN (
    SELECT plu19, 
        CASE WHEN Dstorage IS NOT NULL THEN 'D' END || ' ' || 
        CASE WHEN Nstorage IS NOT NULL THEN 'N' END || ' ' || 
        CASE WHEN STORAG IS NOT NULL THEN 'S' END || ' ' || 
        CASE WHEN Retur IS NOT NULL THEN 'R' END || ' ' || 
        CASE WHEN Hdh IS NOT NULL THEN 'H' END || ' ' || 
        CASE WHEN Sk IS NOT NULL THEN 'SK' END || ' ' || 
        CASE WHEN ENTAH IS NOT NULL THEN 'ENTAH' END AS LKS_JENIS19 
    FROM (
        SELECT DISTINCT Lks_Prdcd AS Plu19, 
            SUM(CASE WHEN Lks_Jenisrak = 'D' THEN 1 ELSE NULL END) AS Dstorage, 
            SUM(CASE WHEN Lks_Jenisrak = 'N' THEN 1 ELSE NULL END) AS Nstorage, 
            SUM(CASE WHEN Lks_Jenisrak = 'S' THEN 1 ELSE NULL END) AS STORAG, 
            SUM(CASE WHEN Lks_Jenisrak = 'R' THEN 1 ELSE NULL END) AS Retur, 
            SUM(CASE WHEN Lks_Jenisrak = 'H' THEN 1 ELSE NULL END) AS Hdh, 
            SUM(CASE WHEN Lks_Koderak LIKE '%C%' THEN 1 ELSE NULL END) AS Sk, 
            SUM(CASE WHEN Lks_Jenisrak NOT IN ('D', 'N', 'R', 'S', 'H') THEN 1 ELSE NULL END) AS ENTAH 
        FROM Tbmaster_Lokasi 
        WHERE Lks_Prdcd IS NOT NULL AND Lks_Qty <> 0 
        GROUP BY Lks_Prdcd
    ) AS y
) AS z ON Prd_Prdcd = PLU19 

-- Subquery AA (Obi Intransit)
LEFT JOIN (
    SELECT SUBSTRING(obi_prdcd, 1, 6) || 0 AS obiplu, SUM(obi_qtyintransit) AS obi_intransit 
    FROM tbtr_obi_d 
    WHERE (obi_tgltrans)::date BETWEEN (CURRENT_DATE - INTERVAL '7 days') AND CURRENT_DATE 
    GROUP BY SUBSTRING(obi_prdcd, 1, 6) || 0
) AS aa ON prd_prdcd = obiplu 

-- Subquery AB (SPI)
LEFT JOIN (
    SELECT spu_prdcd, spu_kodespi AS spi FROM tmp.spi1m_plu_spi
) AS ab ON prd_prdcd = spu_prdcd 
LEFT JOIN (
    SELECT PLU AS PLUPER, SUM(QTY) AS QTYBTBPER, SUM(rpbtb) AS rpbtbPER, TGLBTB as btbpertama 
    FROM (
        SELECT MSTD_PRDCD AS PLU, MIN(MSTD_TGLDOC) AS TGLBTB -- Menggunakan MIN untuk yang pertama
        FROM TBTR_MSTRAN_D 
        WHERE mstd_typetrn IN ('B') 
        GROUP BY MSTD_PRDCD
    ) AS ac 
    LEFT JOIN (
        SELECT MSTD_PRDCD, (mstd_qty + mstd_qtybonus1 + mstd_qtybonus2) AS qty, 
            (mstd_gross - mstd_discrph) + mstd_ppnrph AS rpbtb, (MSTD_TGLDOC) AS TGLBTB1 
        FROM TBTR_MSTRAN_D 
        WHERE mstd_typetrn IN ('B')
    ) AS g ON PLU = MSTD_PRDCD AND TGLBTB = TGLBTB1 
    GROUP BY PLU, TGLBTB
) AS PER ON prd_prdcd = PLUPER
WHERE PRD_PRDCD LIKE '%0' 
ORDER BY 1, 2, 3, 4";
