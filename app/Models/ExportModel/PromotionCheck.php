<?php

namespace App\Models\ExportModel;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class PromotionCheck
{
    /**
     * @return array<int, string>
     */
    public static function normalizeCodes(string $codes): array
    {
        $normalized = array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            preg_split('/[,;\s]+/', $codes, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        return array_values(array_unique(array_filter($normalized)));
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, \stdClass>
     */
    public static function promoDescriptions(array $codes): Collection
    {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = <<<SQL
            SELECT
                'CASHBACK' AS promotion_type,
                cbh_kodepromosi AS promo_code,
                cbh_namapromosi AS promo_name,
                TO_CHAR(cbh_tglawal, 'DD-MON-YY') AS start_date,
                TO_CHAR(cbh_tglakhir, 'DD-MON-YY') AS end_date,
                'POTONGAN' AS mechanism,
                cbh_cashback AS value,
                cbh_create_by AS created_by,
                cbh_minrphprodukpromo AS minimum
            FROM tbtr_cashback_hdr
            WHERE cbh_kodepromosi IN ({$placeholders})

            UNION ALL

            SELECT
                'GIFT' AS promotion_type,
                gfh_kodepromosi AS promo_code,
                gfh_namapromosi AS promo_name,
                TO_CHAR(gfh_tglawal, 'DD-MON-YY') AS start_date,
                TO_CHAR(gfh_tglakhir, 'DD-MON-YY') AS end_date,
                gfh_mekanisme AS mechanism,
                gfh_rphhadiah AS value,
                gfh_create_by AS created_by,
                gfh_jmlhadiah AS minimum
            FROM tbtr_gift_hdr
            WHERE gfh_kodepromosi IN ({$placeholders})
            ORDER BY start_date DESC
            SQL;

        return collect(DB::connection()->select($sql, [...$codes, ...$codes]));
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function suggestPromotions(string $search): Collection
    {
        $pattern = '%'.trim($search).'%';
        $sql = <<<'SQL'
            SELECT promotion_type, promo_code, promo_name
            FROM (
                SELECT
                    'CASHBACK' AS promotion_type,
                    cbh_kodepromosi AS promo_code,
                    cbh_namapromosi AS promo_name
                FROM tbtr_cashback_hdr

                UNION ALL

                SELECT
                    'GIFT' AS promotion_type,
                    gfh_kodepromosi AS promo_code,
                    gfh_namapromosi AS promo_name
                FROM tbtr_gift_hdr
            ) AS promotions
            WHERE promo_code ILIKE ?
                OR promo_name ILIKE ?
            ORDER BY promo_name
            LIMIT 12
            SQL;

        return collect(DB::connection()->select($sql, [$pattern, $pattern]));
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, \stdClass>
     */
    public static function recipients(array $codes): Collection
    {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = <<<SQL
            SELECT
                'CASHBACK' AS promotion_type,
                receipt.kd_member AS member_code,
                customer.cus_namamember AS member_name,
                receipt.trans_no AS receipt_number,
                receipt.kd_promosi AS promo_code,
                TO_CHAR(receipt.tgl_trans, 'DD-MON-YY') AS transaction_date,
                'CASHBACK' AS reward,
                receipt.cashback AS reward_value,
                receipt.create_by AS created_by,
                receipt.kelipatan AS multiplier,
                obi.obi_nopb AS purchase_number
            FROM m_promosi_h AS receipt
            LEFT JOIN tbmaster_customer AS customer
                ON customer.cus_kodemember = receipt.kd_member
            LEFT JOIN tbtr_obi_h AS obi
                ON obi.obi_kdmember = receipt.kd_member
                AND obi.obi_nostruk = receipt.trans_no
                AND obi.obi_tglstruk::date = receipt.tgl_trans::date
                AND obi.obi_cashierid = receipt.create_by
            WHERE receipt.kd_promosi IN ({$placeholders})

            UNION ALL

            SELECT
                'GIFT' AS promotion_type,
                receipt.kd_member AS member_code,
                customer.cus_namamember AS member_name,
                receipt.trans_no AS receipt_number,
                receipt.kd_promosi AS promo_code,
                TO_CHAR(receipt.tgl_trans, 'DD-MON-YY') AS transaction_date,
                receipt.ket_hadiah AS reward,
                receipt.jmlh_hadiah AS reward_value,
                receipt.create_by AS created_by,
                receipt.kelipatan AS multiplier,
                obi.obi_nopb AS purchase_number
            FROM m_gift_h AS receipt
            LEFT JOIN tbmaster_customer AS customer
                ON customer.cus_kodemember = receipt.kd_member
            LEFT JOIN tbtr_obi_h AS obi
                ON obi.obi_kdmember = receipt.kd_member
                AND obi.obi_nostruk = receipt.trans_no
                AND obi.obi_tglstruk::date = receipt.tgl_trans::date
                AND obi.obi_cashierid = receipt.create_by
            WHERE receipt.kd_promosi IN ({$placeholders})
            ORDER BY transaction_date DESC
            SQL;

        return collect(DB::connection()->select($sql, [...$codes, ...$codes]));
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, \stdClass>
     */
    public static function registeredMembers(array $codes): Collection
    {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $igrCode = self::memberIgrCode();
        $sql = <<<SQL
            SELECT
                'CASHBACK' AS promotion_type,
                CASE WHEN member.cbm_recordid IS NULL THEN 'OK' ELSE member.cbm_recordid END AS status,
                member.cbm_kodemember AS member_code,
                customer.cus_namamember AS member_name,
                member.cbm_kodepromosi AS promo_code
            FROM tbtr_cashback_member AS member
            LEFT JOIN tbmaster_customer AS customer
                ON customer.cus_kodemember = member.cbm_kodemember
            WHERE member.cbm_kodepromosi IN ({$placeholders})
                AND customer.cus_kodeigr = ?

            UNION ALL

            SELECT
                'GIFT' AS promotion_type,
                CASE WHEN member.gfm_recordid IS NULL THEN 'OK' ELSE member.gfm_recordid END AS status,
                member.gfm_kodemember AS member_code,
                customer.cus_namamember AS member_name,
                member.gfm_kodepromosi AS promo_code
            FROM tbtr_gift_member AS member
            LEFT JOIN tbmaster_customer AS customer
                ON customer.cus_kodemember = member.gfm_kodemember
            WHERE member.gfm_kodepromosi IN ({$placeholders})
                AND customer.cus_kodeigr = ?
            ORDER BY member_code
            SQL;

        return collect(DB::connection()->select($sql, [
            ...$codes,
            $igrCode,
            ...$codes,
            $igrCode,
        ]));
    }

    public static function memberIgrCode(?string $connectionName = null): string
    {
        $connectionName ??= DB::connection()->getName();

        if (preg_match('/igr_?bms/i', $connectionName) === 1) {
            return '27';
        }

        if (preg_match('/spi(?:bms)?([0-9][a-z0-9]*)(?:_|$)/i', $connectionName, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        throw new LogicException("Unable to determine member IGR code from database connection [{$connectionName}].");
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, \stdClass>
     */
    public static function promoDetails(array $codes): Collection
    {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = <<<SQL
            SELECT
                'CASHBACK' AS promotion_type,
                detail.cbd_prdcd AS plu,
                product.prd_deskripsipanjang AS description,
                detail.cbd_cashback AS value,
                detail.cbd_flagkelipatan AS multiplier,
                detail.cbd_minstruk AS minimum,
                detail.cbd_maxstruk AS maximum,
                detail.cbd_maxmemberperhari AS maximum_per_member,
                detail.cbd_alokasistok AS allocation,
                detail.cbd_kodepromosi AS promo_code,
                detail.cbd_maxcashback AS maximum_reward
            FROM tbtr_cashback_dtl AS detail
            LEFT JOIN tbmaster_prodmast AS product
                ON product.prd_prdcd = detail.cbd_prdcd
            WHERE detail.cbd_kodepromosi IN ({$placeholders})
                AND product.prd_recordid IS NULL

            UNION ALL

            SELECT
                'GIFT' AS promotion_type,
                detail.gfd_prdcd AS plu,
                product.prd_deskripsipanjang AS description,
                detail.gfd_rph AS value,
                NULL::text AS multiplier,
                detail.gfd_pcs AS minimum,
                NULL::numeric AS maximum,
                NULL::numeric AS maximum_per_member,
                NULL::numeric AS allocation,
                detail.gfd_kodepromosi AS promo_code,
                detail.gfd_pcs AS maximum_reward
            FROM tbtr_gift_dtl AS detail
            LEFT JOIN tbmaster_prodmast AS product
                ON product.prd_prdcd = detail.gfd_prdcd
            WHERE detail.gfd_kodepromosi IN ({$placeholders})
            ORDER BY promo_code, plu
            SQL;

        return collect(DB::connection()->select($sql, [...$codes, ...$codes]));
    }
}
