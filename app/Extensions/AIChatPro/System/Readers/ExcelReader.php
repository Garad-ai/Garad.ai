<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class ExcelReader
{
    public function read(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to read spreadsheet: ' . $e->getMessage(), 0, $e);
        }

        $lines = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $lines[] = 'Sheet: ' . $sheet->getTitle();

            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $values = [];
                foreach ($cellIterator as $cell) {
                    $value = trim((string) $cell->getFormattedValue());
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                if ($values !== []) {
                    $lines[] = implode(' | ', $values);
                }
            }
        }

        $text = trim(implode("\n", $lines));

        if ($text === '') {
            throw new RuntimeException('Unable to extract text from the spreadsheet.');
        }

        return $text;
    }
}