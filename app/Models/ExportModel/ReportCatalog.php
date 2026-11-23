<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

final class ReportCatalog
{
    private const WARNING_PERIOD_MONTHS = 12;

    /**
     * @var array<string, int>
     */
    private const MAX_PERIOD_MONTHS = [
        'sales-peritem-member' => 1,
        'exportmember-peritem' => 1,
        'sales-perstruk' => 1,
        'sales-awb' => 6,
        'sales-permember-perbulan' => 6,
        'seles-armada-month-bytglpb-member' => 6,
        'pb-proses-item' => 1,
        'pb-proses' => 6,
        'pb-kirim-list' => 6,
        'pb-belum-pin' => 6,
        'pb-ambil-spi' => 6,
        'pb-request-kirim' => 6,
        'pb-picking-pertanggal' => 1,
        'pb-bark' => 1,
        'pb-sertim' => 6,
        'log-bpb-all' => 1,
        'log-bpb-icc' => 1,
        'log-bpb-nodles' => 1,
        'log-bpb-picsupplier' => 6,
        'log-pb-md' => 1,
        'log-po-all-plu' => 1,
        'log-sl-supplier' => 6,
        'log-tac' => 3,
    ];

    /**
     * @var array<string, int>
     */
    private const MAX_EXPORT_PERIOD_MONTHS = [
        'pb-proses' => 2,
    ];

    /**
     * @var array<string, array{label: string, source: string}>
     */
    private const REPORTS = [
        'sales-peritem' => ['label' => 'Sales per item', 'source' => 'sales'],
        'sales-peritem-member' => ['label' => 'Sales per item member', 'source' => 'sales'],
        'exportmember-peritem' => ['label' => 'Detail item per member', 'source' => 'sales'],
        'sales-peritem-pertgl' => ['label' => 'Sales item per tanggal', 'source' => 'sales'],
        'sales-peritem-perbulan' => ['label' => 'Sales item per bulan', 'source' => 'sales'],
        'sales-permember' => ['label' => 'Sales per member', 'source' => 'sales'],
        'sales-permember-perbulan' => ['label' => 'Sales per member per bulan', 'source' => 'sales'],
        'sales-awb' => ['label' => 'Sales per AWB', 'source' => 'sales'],
        'sales-perstruk' => ['label' => 'Sales per struk', 'source' => 'sales'],
        'seles-armada-month-bytglpb-member' => [
            'label' => 'Sales armada per tanggal PB, member',
            'source' => 'sales',
        ],
        'seles-armada-month-bytglpb' => [
            'label' => 'Sales armada per tanggal PB, bulan',
            'source' => 'sales',
        ],
        'sales-persupplier' => ['label' => 'Sales per supplier per bulan', 'source' => 'sales'],
        'sales-perdepartement' => ['label' => 'Sales per departemen per bulan', 'source' => 'sales'],
        'pb-pertanggal' => ['label' => 'PB per tanggal transaksi', 'source' => 'pb'],
        'pb-proses' => ['label' => 'PB dalam proses', 'source' => 'pb'],
        'pb-proses-item' => ['label' => 'Detail item PB dalam proses', 'source' => 'pb'],
        'pb-kirim-list' => ['label' => 'Daftar PB untuk dikirim', 'source' => 'pb'],
        'pb-belum-pin' => ['label' => 'Daftar PB belum PIN', 'source' => 'pb'],
        'pb-ambil-spi' => ['label' => 'Daftar PB ambil di SPI', 'source' => 'pb'],
        'pb-request-kirim' => ['label' => 'Daftar request kirim', 'source' => 'pb'],
        'pb-picking-pertanggal' => ['label' => 'PB picking per tanggal', 'source' => 'pb'],
        'pb-kirim-pertanggal' => ['label' => 'PB pengiriman per tanggal', 'source' => 'pb'],
        'pb-jam-masuk' => ['label' => 'PB jam masuk', 'source' => 'pb'],
        'pb-bark' => ['label' => 'PB BA Rusak Kemasan', 'source' => 'pb'],
        'pb-sertim' => ['label' => 'PB serah terima Indopaket', 'source' => 'pb'],
        'promo-cb-aktif' => ['label' => 'Promosi cashback aktif', 'source' => 'promotion'],
        'promo-cb-periode' => ['label' => 'Promosi cashback per periode', 'source' => 'promotion'],
        'promo-detail-mypoint' => ['label' => 'Promosi detail transaksi MyPoint', 'source' => 'promotion'],
        'promo-gift-aktif' => ['label' => 'Promosi gift aktif', 'source' => 'promotion'],
        'promo-gift-allho' => ['label' => 'Promosi gift semua HO', 'source' => 'promotion'],
        'promo-gift-periode' => ['label' => 'Promosi gift per periode', 'source' => 'promotion'],
        'promo-item-larangan' => ['label' => 'Promosi item larangan', 'source' => 'promotion'],
        'promo-mypoint' => ['label' => 'Promosi saldo MyPoint per periode', 'source' => 'promotion'],
        'promo-pembatasan' => ['label' => 'Promosi pembatasan transaksi', 'source' => 'promotion'],
        'promo-tidak-ada-promo' => ['label' => 'Promosi item tanpa cashback (2024-2025)', 'source' => 'promotion'],
        'log-bpb-all' => ['label' => 'Logistik BPB semua item', 'source' => 'logistics'],
        'log-bpb-icc' => ['label' => 'Logistik BPB dari ICC', 'source' => 'logistics'],
        'log-bpb-nodles' => ['label' => 'Logistik BPB kategori noodles', 'source' => 'logistics'],
        'log-bpb-picsupplier' => ['label' => 'Logistik BPB PIC supplier', 'source' => 'logistics'],
        'log-kesegaran' => ['label' => 'Logistik kesegaran dan retur supplier', 'source' => 'logistics'],
        'log-master-spi' => ['label' => 'Logistik master SPI', 'source' => 'logistics'],
        'log-pb-md' => ['label' => 'Logistik PB ke MD', 'source' => 'logistics'],
        'log-po-all-plu' => ['label' => 'Logistik PO semua PLU', 'source' => 'logistics'],
        'log-sl-supplier' => ['label' => 'Logistik service level supplier', 'source' => 'logistics'],
        'log-stock' => ['label' => 'Logistik stok dan analisis barang', 'source' => 'logistics'],
        'log-tac' => ['label' => 'Logistik TAC per periode', 'source' => 'logistics'],
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::REPORTS);
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     filters: list<string>,
     *     requiredFilters: list<string>,
     *     maxPeriodMonths: int|null,
     *     warningPeriodMonths: int|null
     * }>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::REPORTS as $value => $report) {
            $options[] = [
                'value' => $value,
                'label' => $report['label'],
                'filters' => self::filtersFor($value),
                'requiredFilters' => self::requiredFiltersFor($value),
                'maxPeriodMonths' => self::maxPeriodMonths($value),
                'warningPeriodMonths' => self::warningPeriodMonths($value),
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function filtersFor(string $report): array
    {
        return match (self::REPORTS[$report]['source'] ?? null) {
            'sales' => [
                'tglstart',
                'tglend',
                'plu',
                'member',
                'supplier',
                'divisi',
                'departement',
                'outlet',
                'sub_outlet',
                'larangan',
                'esse_djarum',
            ],
            'pb' => self::purchaseOrderFilters($report),
            'promotion' => self::promotionFilters($report),
            'logistics' => self::logisticsFilters($report),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function requiredFiltersFor(string $report): array
    {
        $filters = self::filtersFor($report);
        $required = array_values(array_intersect($filters, ['tglstart', 'tglend']));

        if (in_array($report, ['exportmember-peritem', 'promo-detail-mypoint'], true)) {
            $required[] = 'member';
        }

        return $required;
    }

    public static function supportsFilter(string $report, string $filter): bool
    {
        return in_array($filter, self::filtersFor($report), true);
    }

    public static function maxPeriodMonths(string $report): ?int
    {
        return self::MAX_PERIOD_MONTHS[$report] ?? null;
    }

    public static function maxExportPeriodMonths(string $report): ?int
    {
        return self::MAX_EXPORT_PERIOD_MONTHS[$report] ?? self::maxPeriodMonths($report);
    }

    public static function warningPeriodMonths(string $report): ?int
    {
        return self::supportsFilter($report, 'tglstart')
            && self::maxPeriodMonths($report) === null
            ? self::WARNING_PERIOD_MONTHS
            : null;
    }

    /**
     * @return list<string>
     */
    private static function purchaseOrderFilters(string $report): array
    {
        $filters = ['tglstart', 'tglend'];

        if (in_array($report, ['pb-proses-item', 'pb-picking-pertanggal', 'pb-bark'], true)) {
            $filters[] = 'plu';
        }

        return $filters;
    }

    /**
     * @return list<string>
     */
    private static function promotionFilters(string $report): array
    {
        return match ($report) {
            'promo-cb-periode', 'promo-gift-periode' => ['tglstart', 'tglend'],
            'promo-detail-mypoint' => ['member'],
            'promo-mypoint' => ['tglstart', 'tglend', 'member'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function logisticsFilters(string $report): array
    {
        $filters = [];

        if (! in_array($report, ['log-kesegaran', 'log-master-spi', 'log-stock'], true)) {
            $filters = ['tglstart', 'tglend'];
        }

        if ($report !== 'log-tac') {
            $filters[] = 'plu';
        }

        if ($report === 'log-pb-md') {
            $filters[] = 'member';
        }

        if (in_array($report, [
            'log-bpb-all',
            'log-bpb-nodles',
            'log-bpb-picsupplier',
            'log-pb-md',
            'log-po-all-plu',
            'log-sl-supplier',
            'log-stock',
            'log-tac',
        ], true)) {
            $filters[] = 'supplier';
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function query(string $report, array $filters, ?string $connectionName = null): Builder
    {
        $source = self::REPORTS[$report]['source'] ?? null;

        $query = match ($source) {
            'sales' => SalesReport::forExport($report, $filters, $connectionName),
            'pb' => PurchaseOrderReport::forExport($report, $filters, $connectionName),
            'promotion' => PromotionReport::forExport($report, $filters, $connectionName),
            'logistics' => LogisticsReport::forExport($report, $filters, $connectionName),
            default => throw new InvalidArgumentException("Unsupported export report [{$report}]."),
        };

        if (empty($query->orders) && empty($query->unionOrders)) {
            // Laravel Excel chunks FromQuery exports, and Laravel requires ordered queries.
            $query->orderByRaw('1');
        }

        return $query;
    }
}
