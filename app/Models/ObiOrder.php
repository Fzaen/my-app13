<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use stdClass;

class ObiOrder extends Model
{
    protected $table = 'tbtr_obi_h';

    public $timestamps = false;

    /** @return Collection<int, stdClass> */
    public static function getDailySummaryForLastSevenDays(): Collection
    {
        $model = new static;

        return $model->getConnection()
            ->table($model->getTable())
            ->selectRaw('DATE(obi_tglpb) AS tgl')
            ->selectRaw('COUNT(obi_nopb) AS pb')
            ->selectRaw("COUNT(CASE WHEN obi_recid IN ('1', '2', '3', '4', '5', '6') THEN 1 END) AS sudahsend")
            ->selectRaw("COUNT(CASE WHEN obi_recid LIKE 'B%' THEN 1 END) AS batal")
            ->selectRaw('COUNT(CASE WHEN obi_recid IS NULL THEN 1 END) AS belum_sendhh')
            ->selectRaw("COUNT(CASE WHEN obi_recid = '1' AND obi_kdekspedisi LIKE '%Ambil di%' THEN 1 END) AS sp_ambil")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '1' AND obi_kdekspedisi LIKE '%Indopaket%' THEN 1 END) AS sp_indopaket")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '2' THEN 1 END) AS siap_packing")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '3' THEN 1 END) AS siap_dsp")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '5' AND obi_kdekspedisi LIKE '%Ambil%' THEN 1 END) AS siap_struk_spi")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '5' AND obi_kdekspedisi LIKE '%Indopaket%' THEN 1 END) AS siap_struk_ipp")
            ->selectRaw("COUNT(CASE WHEN obi_recid = '6' THEN 1 END) AS selesai_struk")
            ->selectRaw("COUNT(CASE WHEN obi_kdekspedisi LIKE '%Indopaket%' AND obi_recid NOT LIKE 'B%' THEN 1 END) AS indo_paket")
            ->selectRaw("COUNT(CASE WHEN obi_kdekspedisi LIKE '%Ambil di%' AND obi_recid NOT LIKE 'B%' THEN 1 END) AS ambil_ditoko")
            ->whereRaw("DATE(obi_tglpb) BETWEEN CURRENT_DATE - INTERVAL '6 days' AND CURRENT_DATE")
            ->groupByRaw('DATE(obi_tglpb)')
            ->orderByDesc('tgl')
            ->get();
    }
}
