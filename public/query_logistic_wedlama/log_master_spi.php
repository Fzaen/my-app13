<?php

$sql = "SELECT 
spu_kodeigr kodeigr,spu_kodespi	kodespi,
spu_prdcd plu,
prd_deskripsipanjang desk,
prd_kodetag tag,
case when prd_recordid is null then 'Aktif (' || coalesce(prd_recordid,'null') || ')' else 'Not Aktif (' || coalesce(prd_recordid,'null') || ')'  end status_recid,
spu_create_by create_by,
spu_create_dt create_dt,
spu_modify_by modify_by,
spu_modify_dt modify_dt,
spu_pluwajib pluwajib,
st_saldoakhir stock
from tmp.spi1m_plu_spi
left join tbmaster_prodmast on prd_prdcd=spu_prdcd
left join (select * from tbmaster_stock where st_lokasi='01') as st on st_prdcd=spu_prdcd
where spu_kodespi='1M'";
