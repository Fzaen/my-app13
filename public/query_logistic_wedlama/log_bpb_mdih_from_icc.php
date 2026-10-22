<?php
$sql = "SELECT mstd_prdcd plu,
       prd_deskripsipanjang DESK ,
       mstd_frac frac,
       mstd_nopo nopo,
       mstd_nodoc nodoc,
       sum(mstd_gross) gross,
       sum(mstd_qty+mstd_qtybonus1+mstd_qtybonus2) qtybpbicc
                    from tbtr_mstran_d 
                    left join tbmaster_prodmast on mstd_prdcd=prd_prdcd 
                        where MSTD_KODESUPPLIER='I0637' 
                        AND MSTD_NOPO IS NOT NULL 
                        AND MSTD_TGLPO::date >= '2026-04-15'
                        AND mstd_recordid IS NULL
            group by mstd_prdcd,
            prd_deskripsipanjang,
           mstd_frac,
           mstd_nopo,
           mstd_nodoc";
