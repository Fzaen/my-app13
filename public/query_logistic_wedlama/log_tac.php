<?php

$sql = "SELECT TO_CHAR(MSTD_TGLDOC,'DD-MON-YY') TGL,MSTD_PRDCD,PRD_DESKRIPSIPANJANG DESK,prd_frac,MSTD_AVGCOST,SUM(MSTD_QTY) QTY,
SUM(MSTD_GROSS) GROSS,KOSUP,NASUP
FROM TBTR_MSTRAN_D
LEFT JOIN TBMASTER_PRODMAST ON MSTD_PRDCD = PRD_PRDCD
left join (select DISTINCT(hgb_prdcd) plu2,hgb_kodesupplier KOSUP,
                                         sup_namasupplier NASUP
                                         from tbmaster_hargabeli left join tbmaster_supplier on (hgb_kodesupplier=sup_kodesupplier)
                                         where hgb_tipe='2' ) as a on plu2=MSTD_PRDCD
WHERE MSTD_TYPETRN='I' 
AND MSTD_TGLDOC::DATE BETWEEN '{$ptglstart}' AND '{$ptglend}'
group by MSTD_PRDCD,PRD_DESKRIPSIPANJANG ,prd_frac,MSTD_AVGCOST,TO_CHAR(MSTD_TGLDOC,'DD-MON-YY'),KOSUP,NASUP";

// $sql = "SELECT MSTD_PRDCD,PRD_DESKRIPSIPANJANG DESK,prd_frac,ROUND(COALESCE(SALES,0)/(DATE_PART('month', AGE('$ptglend', '$ptglstart')) 
//       + (DATE_PART('year', AGE('$ptglend', '$ptglstart')) * 12)+1)) RATA2_SLS ,SUM(MSTD_QTY) QTY,SUM(MSTD_GROSS) GROSS FROM TBTR_MSTRAN_D
// LEFT JOIN TBMASTER_PRODMAST ON MSTD_PRDCD = PRD_PRDCD
// left join (SELECT SUBSTRING(TRJD_PRDCD,1,6)||0 PLU,
// SUM($viewsales_net) SALES
// 			  FROM TBTR_JUALDETAIL 
//                     LEFT JOIN TBMASTER_PRODMAST ON TRJD_PRDCD=PRD_PRDCD
//                     WHERE TRJD_TRANSACTIONDATE::DATE BETWEEN '{$ptglstart}' AND '{$ptglend}'
// 			  GROUP BY SUBSTRING(TRJD_PRDCD,1,6)||0 ) AS A ON PLU = MSTD_PRDCD
// WHERE MSTD_TYPETRN='I' 
// AND MSTD_TGLDOC::DATE BETWEEN '{$ptglstart}' AND '{$ptglend}'
// group by MSTD_PRDCD,PRD_DESKRIPSIPANJANG ,prd_frac,
// ROUND(COALESCE(SALES,0)/(DATE_PART('month', AGE('$ptglend', '$ptglstart')) 
//       + (DATE_PART('year', AGE('$ptglend', '$ptglstart')) * 12)+1))";
