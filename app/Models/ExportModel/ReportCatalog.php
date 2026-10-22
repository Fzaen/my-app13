<?php

namespace App\Models\ExportModel;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

final class ReportCatalog
{
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
        'pb-peritem' => ['label' => 'PB detail per item', 'source' => 'pb'],
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
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::REPORTS as $value => $report) {
            $options[] = ['value' => $value, 'label' => $report['label']];
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function query(string $report, array $filters, ?string $connectionName = null): Builder
    {
        $source = self::REPORTS[$report]['source'] ?? null;

        return match ($source) {
            'sales' => SalesReport::forExport($report, $filters, $connectionName),
            'pb' => PurchaseOrderReport::forExport($report, $filters, $connectionName),
            'promotion' => PromotionReport::forExport($report, $filters, $connectionName),
            'logistics' => LogisticsReport::forExport($report, $filters, $connectionName),
            default => throw new InvalidArgumentException("Unsupported export report [{$report}]."),
        };
    }
}
