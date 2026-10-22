<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class TextColumnValueBinder extends DefaultValueBinder implements WithCustomValueBinder
{
    private const TEXT_COLUMNS = [
        'plu',
        'prdcd',
        'prd_prdcd',
        'ktp',
        'dept',
        'katb',
        'dep',
        'trjd_prdcd',
        'div',
        'member',
        'nopb',
        'notrans',
        'nostruk',
        'nosj',
        'noawb',
        'pb',
        'pincode',
        'container',
        'urut_pick',
        'kodesupplier',
        'kode',
        'trx',
        'no_hp',
        'toko',
        'recordid',
    ];

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getRow() > 1 && ($worksheet = $cell->getWorksheet()) !== null) {
            $coordinate = $cell->getCoordinate();

            if (
                $this->isTextColumn($worksheet, $cell->getColumn())
                && (is_scalar($value) || $value === null)
            ) {
                $worksheet->getCell($coordinate)->setValueExplicit((string) $value, DataType::TYPE_STRING);

                return true;
            }

            return parent::bindValue($worksheet->getCell($coordinate), $value);
        }

        return parent::bindValue($cell, $value);
    }

    private function isTextColumn(Worksheet $worksheet, string $column): bool
    {
        $heading = $worksheet->getCell($column.'1')->getValue();

        if (! is_string($heading)) {
            return false;
        }

        $heading = mb_strtolower($heading);

        foreach (self::TEXT_COLUMNS as $textColumn) {
            if (str_contains($heading, $textColumn)) {
                return true;
            }
        }

        return false;
    }
}
