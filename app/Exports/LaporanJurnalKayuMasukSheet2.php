<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class LaporanJurnalKayuMasukSheet2 extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithCustomValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
    protected array $jurnalTables;

    public function __construct(array $jurnalTables)
    {
        $this->jurnalTables = $jurnalTables;
    }

    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $isSengon = (stripos($jenisKayuNama, 'sengon') !== false);
        $is130 = ((int) $panjang === 130);

        if ($isSengon) {
            if (!$is130) {
                return [
                    'no_akun' => '1411.01',
                    'nama_akun' => 'Kayu Lunak 260 WJY',
                ];
            } else {
                return [
                    'no_akun' => '1411.03',
                    'nama_akun' => 'Kayu Lunak 130 WJY',
                ];
            }
        } else {
            if (!$is130) {
                return [
                    'no_akun' => '1411.02',
                    'nama_akun' => 'Kayu Keras 260 WJY',
                ];
            } else {
                return [
                    'no_akun' => '1411.04',
                    'nama_akun' => 'Kayu Keras 130 WJY',
                ];
            }
        }
    }

    public function collection()
    {
        $flatRows = [];

        foreach ($this->jurnalTables as $table) {
            $noJurnal = "MASUK/" . \Carbon\Carbon::parse($table['tgl_kayu_masuk'])->format('Ymd') . "/" . $table['no_nota'];
            $tglVal = \Carbon\Carbon::parse($table['tgl_kayu_masuk'])->format('d/m/Y');

            // Table Header Block
            $flatRows[] = ['No. Jurnal: ' . $noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''];
            
            // Column Headers
            $flatRows[] = [
                'Nama Akun',
                'tgl',
                'jur',
                'No Akun',
                'No',
                'mm',
                'Nama Suplier',
                'Lahan',
                'm',
                'hit kbk',
                'Banyak',
                'M3',
                'Harga',
                'Total'
            ];

            // 1. Add Debit entries from groups
            foreach ($table['groups'] as $group) {
                $jenisKayuNama = $group['jenis'] ?? '';
                $panjang = $group['panjang'] ?? 130;
                $kodeLahan = $group['kode_lahan'] ?? '';

                // Graceful fallback parsing in case of old serialized Livewire component state
                if (empty($jenisKayuNama) && !empty($group['header'])) {
                    $header = $group['header'];
                    
                    if (preg_match('/(\d+)\s*cm/', $header, $matches)) {
                        $panjang = (int) $matches[1];
                    }
                    
                    if (preg_match('/cm\s+(.+?)\s+\(/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    } elseif (preg_match('/cm\s+(.+)$/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    }
                }

                if (empty($kodeLahan) && !empty($group['header'])) {
                    $tokens = preg_split('/\s+/', trim($group['header']));
                    if (!empty($tokens)) {
                        $kodeLahan = $tokens[0];
                    }
                }

                $acc = $this->getAccountDetails($jenisKayuNama, $panjang);

                $flatRows[] = [
                    $acc['nama_akun'],
                    $tglVal,
                    '',
                    $acc['no_akun'],
                    $table['seri'],
                    '',
                    $table['nama_supplier'],
                    $kodeLahan,
                    'd',
                    'm',
                    $group['total_batang'],
                    $group['total_kubikasi'],
                    $group['total_harga'],
                    $group['total_harga']
                ];
            }

            // 2. Add Credit Row 1: hutang ongkos turun kayu
            $flatRows[] = [
                'hutang ongkos turun kayu',
                $tglVal,
                '',
                '2400.01',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                '',
                '',
                $table['selisih'],
                $table['selisih']
            ];

            // 3. Add Credit Row 2: pendapatan
            $flatRows[] = [
                'pendapatan',
                $tglVal,
                '',
                '4000.00',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                '',
                '',
                '',
                ''
            ];

            // 4. Add Credit Row 3: Kas Mut
            $flatRows[] = [
                'Kas Mut',
                $tglVal,
                '',
                '1111.00',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                $table['totalBatang'],
                $table['totalKubikasi'],
                $table['hargaFinal'],
                $table['hargaFinal']
            ];

            // Spacer Rows between multiple tables
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        return collect($flatRows);
    }

    public function title(): string
    {
        return 'Jurnal Kayu Masuk';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            if (str_starts_with((string)$cellValue, 'No. Jurnal:')) {
                // Style the Title block of each table
                $sheet->mergeCells("A{$row}:N{$row}");
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['argb' => 'FF1D2939']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFD2E4F0']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif ($cellValue === 'Nama Akun') {
                // Style Table Column Headers
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E8EB']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif (!empty($cellValue)) {
                // Style data rows
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);

                // Alignments
                $sheet->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$row}:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Formats
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("M{$row}:N{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // Widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10); // mm
        $sheet->getColumnDimension('G')->setWidth(25); // Nama Suplier
        $sheet->getColumnDimension('H')->setWidth(10); // Lahan
        $sheet->getColumnDimension('I')->setWidth(10); // m
        $sheet->getColumnDimension('J')->setWidth(12); // hit kbk
        $sheet->getColumnDimension('K')->setWidth(12); // Banyak
        $sheet->getColumnDimension('L')->setWidth(15); // M3
        $sheet->getColumnDimension('M')->setWidth(18); // Harga
        $sheet->getColumnDimension('N')->setWidth(18); // Total

        return [];
    }
}
