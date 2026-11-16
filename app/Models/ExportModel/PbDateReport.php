<?php

namespace App\Models\ExportModel;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PbDateReport
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        'pb',
        'tgl',
        'obi_nopb',
        'obi_notrans',
        'obi_kdmember',
        'cus_namamember',
        'cus_alamatmember8',
        'tglstruk',
        'obi_tipebayar',
        'pin',
        'obi_cod_pincode',
        'ket_pin',
        'ttl_order',
        'obi_itemorder',
        'ttl_real',
        'obi_realitem',
        'obi_jrkekspedisi',
        'awi_cost',
        'ads_hadiah',
        'obi_ekspedisi',
        'jenis',
        'jam_dsp',
        'loyalty',
        'req',
        'ket',
        'obi_service_fee',
        'ketdsp',
        'kdpromo',
        'hdh_qty',
    ];

    /**
     * @param  array{
     *     tglstart: string,
     *     tglend: string,
     *     recid?: string,
     *     ekspedisi?: string,
     *     pin?: string,
     *     kdmm?: string,
     *     nopb?: string,
     *     request?: bool
     * }  $filters
     */
    public static function query(
        array $filters,
        ?string $connectionName = null,
        string $search = '',
    ): Builder {
        $connection = DB::connection($connectionName);
        $endExclusive = CarbonImmutable::parse($filters['tglend'])->addDay()->toDateString();
        $filteredOrders = $connection->table('tbtr_obi_h')
            ->selectRaw('OBI_NOpb')
            ->selectRaw("RTRIM(SUBSTRING(OBI_NOpb, 1, 7), '/') AS pb")
            ->selectRaw('obi_createdt AS tgl')
            ->selectRaw('obi_notrans::NUMERIC AS obi_notrans')
            ->selectRaw('obi_kdmember')
            ->selectRaw('obi_nostruk')
            ->selectRaw('obi_tglstruk::DATE AS tglstruk')
            ->selectRaw('obi_tipe')
            ->selectRaw('obi_kdstation')
            ->selectRaw('obi_cashierid')
            ->selectRaw('obi_ekspedisi')
            ->selectRaw('obi_kdekspedisi')
            ->selectRaw('obi_flagbayar')
            ->selectRaw('obi_tipebayar')
            ->selectRaw('obi_service_fee')
            ->selectRaw('obi_cod_pincode')
            ->selectRaw('obi_itemorder')
            ->selectRaw('obi_realitem')
            ->selectRaw('obi_jrkekspedisi')
            ->selectRaw('OBI_RECID')
            ->selectRaw('obi_tglpb')
            ->whereRaw('obi_tglpb >= ?::date', [$filters['tglstart']])
            ->whereRaw('obi_tglpb < ?::date', [$endExclusive]);

        self::applyOrderFilters($filteredOrders, $filters);

        $sql = <<<'SQL'
            WITH filtered_obi AS (
                %s
            ),
            awb_data AS (
                SELECT AWI_NOpb, AWI_NOORDER, awi_pincode, awi_cost
                FROM TBTR_AWB_IPP
            ),
            serah_terima AS (
                SELECT STI_NOORDER, STI_pin, sti_receivername
                FROM TBTR_SERAHTERIMA_IPP
            ),
            aggregated_total AS (
                SELECT
                    OBI_TGLTRANS AS DT_TGLTRANS,
                    OBI_NOTRANS AS DT_NOTRANS,
                    SUM((OBI_HARGASATUAN + OBI_PPN - OBI_DISKON) * OBI_QTYREALISASI)
                        - COALESCE(MAX(CB_REAL), 0) AS TTL_REAL,
                    SUM((OBI_HARGASATUAN + OBI_PPN) * OBI_QTYORDER)
                        - COALESCE(MAX(CB_ORDER), 0) AS TTL_ORDER
                FROM TBTR_OBI_D
                LEFT JOIN (
                    SELECT NO_PB, no_trans, tgl_trans,
                           SUM(CASHBACK_ORDER) AS CB_ORDER,
                           SUM(CASHBACK_REAL) AS CB_REAL
                    FROM PROMO_KLIKIGR
                    WHERE TIPE_PROMO = 'CASHBACK'
                      AND TGL_TRANS >= ?::date
                      AND TGL_TRANS < ?::date
                    GROUP BY NO_PB, no_trans, tgl_trans
                ) CB ON OBI_notrans = no_trans AND OBI_tgltrans = tgl_trans
                WHERE OBI_TGLTRANS >= ?::date
                  AND OBI_TGLTRANS < ?::date
                GROUP BY OBI_TGLTRANS, OBI_NOTRANS
            ),
            hadiah_ranked AS (
                SELECT
                    obi_kdmember,
                    obi_tgltrans,
                    obi_nopb,
                    total,
                    ads_kode,
                    ads_prdcd,
                    ads_qty,
                    ads_hadiah,
                    HDH_QTY,
                    ROW_NUMBER() OVER (
                        PARTITION BY obi_kdmember, obi_tgltrans
                        ORDER BY
                            CASE WHEN hdh_qty IS NOT NULL THEN 1 ELSE 2 END,
                            total DESC,
                            ads_sponsor DESC
                    ) AS rn
                FROM (
                    SELECT
                        COALESCE(OBI_RECID, '1') AS RECID,
                        OBI_KDMEMBER,
                        OBI_TGLTRANS::DATE AS OBI_TGLTRANS,
                        OBI_NOPB,
                        TTLORDER,
                        TTLREAL,
                        CB_ORDER,
                        CB_REAL,
                        ROUND(
                            CASE
                                WHEN COALESCE(OBI_RECID, '1') IN ('5', '6')
                                THEN COALESCE(TTLREAL, 0) - COALESCE(CB_REAL, 0)
                                ELSE COALESCE(TTLORDER, 0) - COALESCE(CB_ORDER, 0)
                            END
                        ) AS total,
                        HDH_QTY,
                        ads_sponsor,
                        ads_tglawal,
                        ads_akhir,
                        ads_kode,
                        ads_prdcd,
                        ads_qty,
                        ads_hadiah
                    FROM TBTR_OBI_H
                    LEFT JOIN (
                        SELECT
                            OBI_TGLTRANS AS TGLTRANS,
                            OBI_NOTRANS AS NOTRANS,
                            SUM(
                                (COALESCE(OBI_HARGASATUAN, 0) + COALESCE(OBI_PPN, 0)
                                    - COALESCE(obi_diskon, 0)) * COALESCE(OBI_QTYREALISASI, 0)
                            ) AS TTLREAL,
                            SUM(
                                (COALESCE(OBI_HARGASATUAN, 0) + COALESCE(OBI_PPN, 0)
                                    - COALESCE(obi_diskon, 0)) * COALESCE(OBI_QTYORDER, 0)
                            ) AS TTLORDER
                        FROM TBTR_OBI_D
                        WHERE OBI_TGLTRANS >= ?::date
                          AND OBI_TGLTRANS < ?::date
                          AND SUBSTR(OBI_PRDCD, 1, 6) || '0'
                              NOT IN (SELECT NON_PRDCD FROM TBMASTER_PLUNONPROMO)
                        GROUP BY OBI_TGLTRANS, OBI_NOTRANS
                    ) D ON OBI_TGLTRANS = TGLTRANS AND OBI_NOTRANS = NOTRANS
                    LEFT JOIN (
                        SELECT NO_PB,
                               SUM(COALESCE(CASHBACK_ORDER, 0)) AS CB_ORDER,
                               SUM(COALESCE(CASHBACK_REAL, 0)) AS CB_REAL
                        FROM PROMO_KLIKIGR
                        WHERE TIPE_PROMO = 'CASHBACK'
                          AND TGL_TRANS >= ?::date
                          AND TGL_TRANS < ?::date
                        GROUP BY NO_PB
                    ) CB ON SUBSTR(NO_PB, 1, 7) = SUBSTR(OBI_NOPB, 1, 7)
                    LEFT JOIN TMP.HADIAH ON SUBSTR(OBI_NOPB, 1, 7) = HDH_NOPB
                    LEFT JOIN TMP.PROMO_ADS ON 1 = 1
                    WHERE OBI_KDEKSPEDISI = 'Ambil di Stock Point Indogrosir'
                      AND OBI_TGLPB >= ?::date
                      AND OBI_TGLPB < ?::date
                      AND COALESCE(OBI_RECID, '1') NOT LIKE 'B%'
                ) sub_hadiah
                WHERE total >= ads_sponsor
                  AND obi_tgltrans BETWEEN ads_tglawal AND ads_akhir
            ),
            indomie_data AS (
                SELECT
                    obi_kdmember AS mmh,
                    obi_tgltrans AS tglh,
                    obi_nopb AS pbhadiah,
                    total AS total_terbesar,
                    HDH_QTY,
                    ads_kode,
                    ads_prdcd,
                    ads_qty,
                    ads_hadiah
                FROM hadiah_ranked
                WHERE rn = 1
                  AND ads_kode IS NOT NULL
            )
            SELECT DISTINCT
                f.pb,
                f.tgl,
                f.obi_tglpb::date AS obi_tglpb,
                f.obi_nopb,
                f.obi_notrans,
                f.obi_kdmember,
                c.cus_namamember,
                c.cus_alamatmember8,
                f.obi_nostruk,
                f.tglstruk,
                f.obi_tipe,
                f.obi_kdstation,
                f.obi_cashierid,
                f.obi_ekspedisi,
                f.obi_kdekspedisi,
                f.obi_flagbayar,
                f.obi_tipebayar,
                b.awi_pincode,
                f.obi_service_fee,
                CASE
                    WHEN c_rec.awi_pincode IS NOT NULL
                    THEN 'RECREATE ' || c_rec.awi_pincode
                    ELSE b.awi_pincode
                END AS pin,
                CASE
                    WHEN f_d.STI_pin IS NOT NULL THEN 'SUDAH pin B 2x'
                    WHEN f_c.STI_pin IS NOT NULL THEN 'SUDAH INPUT pin RECREATE'
                    WHEN f_b.STI_pin IS NOT NULL THEN 'SUDAH pin B 1x'
                    WHEN st.sti_pin IS NOT NULL AND st.sti_receivername = '' THEN 'MASIH TMS'
                    WHEN st.sti_pin IS NOT NULL AND st.sti_receivername != '' THEN 'SUDAH INPUT pin'
                    WHEN f.obi_kdekspedisi LIKE '%Ambil%' AND tempat.AMBIL IS NULL
                        THEN 'BELUM PERNAH AMBIL DI SPI'
                    WHEN f.obi_kdekspedisi LIKE '%Ambil%' THEN 'AMBIL DI SPI'
                    ELSE 'BELUM INPUT pin'
                END AS ket_pin,
                b.awi_cost,
                f.obi_cod_pincode,
                tot.TTL_ORDER AS ttl_order,
                f.obi_itemorder,
                tot.TTL_REAL AS ttl_real,
                f.obi_realitem,
                prmc.KDPROMO,
                CASE
                    WHEN f.obi_kdekspedisi LIKE '%Indopaket%' THEN 'INDOPAket'
                    WHEN f.obi_kdekspedisi LIKE '%Ambil%' THEN 'AMBIL DI SPI'
                END AS jenis,
                f.obi_jrkekspedisi,
                CASE
                    WHEN f.OBI_RECID LIKE 'B%' THEN 'BATAL'
                    WHEN f.OBI_RECID IS NULL THEN 'BLM_SEND'
                    WHEN f.OBI_RECID = '1' THEN 'PICKING'
                    WHEN f.OBI_RECID = '2' THEN 'PACKING'
                    WHEN f.OBI_RECID = '3' THEN 'SIAP_DSP'
                    WHEN f.OBI_RECID = '5' THEN 'DSP'
                    WHEN f.OBI_RECID = '6' THEN 'STRUK'
                END AS ket,
                g.jam_dsp,
                CASE
                    WHEN f.OBI_RECID = '5'
                         AND f.obi_kdekspedisi LIKE '%Indopaket%'
                         AND g.DSP_TOTALDSP IS NULL
                    THEN 'ULANG'
                    ELSE ''
                END AS ketdsp,
                req.req_tgl_kirim || ' ' || req.req_request AS req,
                h_loy.LOYALTY,
                gt.gttipe,
                ind.ads_kode,
                ind.ads_prdcd,
                ind.ads_qty,
                ind.ads_hadiah,
                ind.HDH_QTY
            FROM filtered_obi f
            LEFT JOIN indomie_data ind
                ON SUBSTRING(f.OBI_NOpb, 1, 7) = SUBSTRING(ind.pbhadiah, 1, 7)
            LEFT JOIN aggregated_total tot
                ON f.obi_tglpb::date = tot.DT_TGLTRANS
                AND f.obi_notrans = tot.DT_NOTRANS::numeric
            LEFT JOIN (
                SELECT OBI_KDMEMBER AS MM_AMBIL, COUNT(OBI_NOPB) AS AMBIL
                FROM tbtr_obi_h
                WHERE obi_kdekspedisi = 'Ambil di Stock Point Indogrosir'
                  AND obi_recid = '6'
                GROUP BY OBI_KDMEMBER
            ) AS tempat ON f.obi_kdmember = tempat.MM_AMBIL
            LEFT JOIN (
                SELECT CUS_KODEMEMBER, cus_namamember, cus_alamatmember8
                FROM TBMASTER_CUSTOMER
                WHERE CUS_KODEIGR = '1M'
            ) AS c ON f.obi_kdmember = c.CUS_KODEMEMBER
            LEFT JOIN awb_data b ON f.pb = b.AWI_NOORDER
            LEFT JOIN (
                SELECT AWI_NOORDER AS pbRECREATE, awi_pincode
                FROM awb_data
                WHERE AWI_NOORDER LIKE 'A%'
            ) AS c_rec ON f.pb = SUBSTRING(c_rec.pbRECREATE, 2, 8)
            LEFT JOIN serah_terima st
                ON UPPER(SUBSTRING(b.awi_pincode, 1, 1))
                    = UPPER(SUBSTRING(st.STI_pin, 1, 1))
                AND st.STI_NOORDER = f.pb
            LEFT JOIN (
                SELECT STI_NOORDER AS ORDER_B, STI_pin
                FROM serah_terima
                WHERE STI_pin LIKE 'B%'
            ) AS f_b ON f.pb = f_b.ORDER_B
            LEFT JOIN (
                SELECT STI_NOORDER AS ORDER_C, STI_pin
                FROM serah_terima
                WHERE STI_NOORDER LIKE 'A%' AND STI_pin LIKE 'A%'
            ) AS f_c ON f.pb = SUBSTRING(f_c.ORDER_C, 2, 8)
            LEFT JOIN (
                SELECT STI_NOORDER AS ORDER_D, STI_pin
                FROM serah_terima
                WHERE STI_NOORDER LIKE 'A%' AND STI_pin LIKE 'B%'
            ) AS f_d ON f.pb = SUBSTRING(f_d.ORDER_D, 2, 8)
            LEFT JOIN (
                SELECT DSP_NOpb, DSP_tglpb, DSP_TOTALDSP,
                       TO_CHAR(DSP_CREATE_DT, 'DD-MON-YY HH24:MI:SS') AS jam_dsp
                FROM tbtr_dsp_spi
                WHERE DSP_tglpb >= ?::date
                  AND DSP_tglpb < ?::date
            ) AS g
                ON RTRIM(SUBSTRING(g.DSP_NOpb, 1, 7), '/') = f.pb
                AND f.obi_tglpb::date = g.DSP_tglpb::date
            LEFT JOIN tmp.spi1m_requestkirim req
                ON f.pb = CAST(req.REQ_NOpb AS TEXT)
                AND f.obi_tglpb::date = req.REQ_tglpb::date
                AND f.obi_kdmember = req.REQ_KODEMM
            LEFT JOIN (
                SELECT ly_kode AS gtmm, LY_PROGRAM AS gttipe
                FROM tmp.spi1m_loyalty
                WHERE LY_PERIODE = '2025' AND ly_tgl_kirim IS NULL
            ) AS gt ON gt.gtmm = f.obi_kdmember
            LEFT JOIN (
                SELECT LY_KODE,
                       SUM(CASE WHEN LY_TTD = '' OR LY_TTD IS NULL THEN 1 ELSE 0 END) AS LOYALTY
                FROM tmp.spi1m_loyalty
                WHERE ly_tgl_kirim IS NULL
                GROUP BY LY_KODE
            ) AS h_loy ON f.obi_kdmember = h_loy.LY_KODE
            LEFT JOIN (
                SELECT NO_PB AS PNPROMO, KODE_PROMO AS KDPROMO
                FROM PROMO_KLIKIGR
                WHERE KODE_PROMO = 'CKKN3'
            ) AS prmc ON SUBSTRING(prmc.PNPROMO, 1, 7) = SUBSTRING(f.OBI_NOpb, 1, 7)
            SQL;

        $bindings = [
            ...$filteredOrders->getBindings(),
            $filters['tglstart'],
            $endExclusive,
            $filters['tglstart'],
            $endExclusive,
            $filters['tglstart'],
            $endExclusive,
            $filters['tglstart'],
            $endExclusive,
            $filters['tglstart'],
            $endExclusive,
            $filters['tglstart'],
            $endExclusive,
        ];
        $querySql = str_replace('%s', $filteredOrders->toSql(), $sql);
        $query = $connection->query()
            ->fromRaw('('.$querySql.') AS pb_tgl', $bindings)
            ->select('pb_tgl.*');

        if (($filters['pin'] ?? '') === 'belumpin') {
            $query->whereIn('pb_tgl.ket_pin', ['BELUM INPUT pin', 'SUDAH pin B 1x']);
        } elseif (($filters['pin'] ?? '') === 'sudahpin') {
            $query->whereIn('pb_tgl.ket_pin', ['SUDAH INPUT pin', 'SUDAH INPUT pin RECREATE']);
        }

        if (($filters['request'] ?? false) === true) {
            $query->whereNotNull('pb_tgl.req');
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                foreach (self::COLUMNS as $index => $column) {
                    $condition = 'CAST("pb_tgl"."'.$column.'" AS TEXT) ILIKE ?';
                    $binding = '%'.$search.'%';

                    if ($index === 0) {
                        $searchQuery->whereRaw($condition, [$binding]);
                    } else {
                        $searchQuery->orWhereRaw($condition, [$binding]);
                    }
                }
            });
        }

        return $query
            ->orderByDesc('pb_tgl.tgl')
            ->orderBy('pb_tgl.obi_notrans')
            ->orderBy('pb_tgl.obi_nopb');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function applyOrderFilters(Builder $query, array $filters): void
    {
        match ($filters['recid'] ?? '') {
            'notnull' => $query->whereNotNull('obi_recid'),
            'null' => $query->whereNull('obi_recid'),
            'B' => $query->where('obi_recid', 'like', 'B%'),
            'proses' => $query->where(function (Builder $statusQuery): void {
                $statusQuery->whereIn('obi_recid', ['1', '2', '3', '4', '5'])
                    ->orWhereNull('obi_recid');
            }),
            'batas' => $query->where(function (Builder $statusQuery): void {
                $statusQuery->whereIn('obi_recid', ['1', '2', '3'])
                    ->orWhereNull('obi_recid');
            }),
            '1', '2', '3', '5', '6' => $query->where('obi_recid', $filters['recid']),
            default => null,
        };

        if (isset($filters['ekspedisi']) && $filters['ekspedisi'] !== '') {
            $query->where('obi_kdekspedisi', 'like', $filters['ekspedisi'].'%');
        }

        if (isset($filters['kdmm']) && $filters['kdmm'] !== '') {
            $query->where('obi_kdmember', $filters['kdmm']);
        }

        if (isset($filters['nopb']) && $filters['nopb'] !== '') {
            $query->where('obi_nopb', 'like', $filters['nopb'].'%');
        }
    }
}
