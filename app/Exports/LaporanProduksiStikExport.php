<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================================================
//  MAIN EXPORT — membungkus 2 sheet
// ============================================================
class LaporanProduksiStikExport implements WithMultipleSheets
{
    protected array $dataStik;

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiStikSheetPekerja($this->dataStik),
            new LaporanProduksiStikSheetHasil($this->dataStik),
        ];
    }
}

// ============================================================
//  SHEET 1 — "Laporan Produksi Stik" (tidak diubah)
// ============================================================
class LaporanProduksiStikSheetPekerja implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $dataStik;

    public function __construct(array $dataStik)
    {
        $this->dataStik = collect($dataStik);
    }

    public function collection(): Collection
    {
        $rows = collect();

        if ($this->dataStik->isEmpty()) {
            return $rows;
        }

        $rows->push(['LAPORAN PRODUKSI STIK']);
        $rows->push(['Tanggal:', $this->dataStik->first()['tanggal'] ?? '']);
        $rows->push([]);

        $index = 0;
        foreach ($this->dataStik as $produksi) {
            $pekerja       = $produksi['pekerja']       ?? [];
            $kendala       = $produksi['kendala']       ?? 'Tidak ada kendala.';
            $target        = $produksi['target_harian'] ?? 0;
            $jamKerja      = $produksi['jam_kerja']     ?? 0;
            $hasil         = $produksi['hasil_harian']  ?? 0;
            $selisih       = $produksi['selisih']       ?? 0;
            $totalPekerja  = count($pekerja);
            $selisihTampil = $selisih * -1;

            $rows->push(['PRODUKSI STIK - Entri ke-' . ($index + 1)]);
            $rows->push(['RINGKASAN HARIAN']);
            $rows->push(['Target Harian:',      (int) $target]);
            $rows->push(['Jam Kerja:',           (int) $jamKerja]);
            $rows->push(['Total Hasil:',         (int) $hasil]);
            $rows->push(['Selisih (vs Target):', (int) $selisihTampil]);
            $rows->push(['Total Pekerja:',       $totalPekerja . ' orang']);
            $rows->push(['Kendala:',             $kendala]);
            $rows->push([]);

            $rows->push(['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target (Rp)', 'Keterangan']);

            foreach ($pekerja as $p) {
                $potTargetRaw = (int) str_replace(['.', 'Rp ', '-'], '', $p['pot_target'] ?? '0');
                $rows->push([
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $p['jam_masuk']  ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin']       ?? '-',
                    $potTargetRaw > 0 ? (int) $potTargetRaw : 0,
                    $p['keterangan'] ?? '-',
                ]);
            }

            $rows->push([]);
            $rows->push([]);
            $index++;
        }

        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string   { return 'Laporan Produksi Stik'; }
}

// ============================================================
//  SHEET 2 — "Hasil Stik" (bergaya dryer, merge cell, tanpa ringkasan)
// ============================================================
class LaporanProduksiStikSheetHasil implements FromArray, WithTitle, WithStyles
{
    protected array $dataStik;
    protected array $styleMap    = [];
    protected array $mergeRanges = [];

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function array(): array
    {
        $rows     = [];
        $rowIndex = 1;

        if (empty($this->dataStik)) {
            $rows[] = ['Tidak ada data untuk tanggal ini.'];
            return $rows;
        }

        foreach ($this->dataStik as $produksi) {
            $tanggal      = $produksi['tanggal']     ?? '-';
            $pekerja      = $produksi['pekerja']      ?? [];
            $detailHasil  = $produksi['detail_hasil'] ?? [];
            $totalPekerja = count($pekerja);

            // ── JUDUL SEKSI ──────────────────────────────────────
            $rows[] = ['PRODUKSI STIK', '', '', '', '', '', '', '', '', '', ''];
            $this->styleMap[$rowIndex] = 'section_title';
            $rowIndex++;

            // ── HEADER KOLOM ─────────────────────────────────────
            $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'byk', 'TTL PKJ'];
            $this->styleMap[$rowIndex] = 'col_header';
            $rowIndex++;

            // ── DATA ROWS ────────────────────────────────────────
            $dataStartRow = $rowIndex;

            if (empty($detailHasil)) {
                $rows[] = [$tanggal, '-', '-', '-', '-', '', '', '', '', '', $totalPekerja];
                $this->styleMap[$rowIndex] = 'data';
                $rowIndex++;
            } else {
                foreach ($detailHasil as $i => $detail) {
                    $rows[] = [
                        $i === 0 ? $tanggal      : '',
                        $detail['panjang']    ?? '-',
                        $detail['lebar']      ?? '-',
                        $detail['tebal']      ?? '-',
                        $detail['jenis_kayu'] ?? '-',
                        $detail['kw1']        ?? '',
                        $detail['kw2']        ?? '',
                        $detail['kw3']        ?? '',
                        $detail['kw4']        ?? '',
                        $detail['total']      ?? '',
                        $i === 0 ? $totalPekerja : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                // Merge Tanggal & TTL PKJ hanya jika ada lebih dari 1 baris data
                if (count($detailHasil) > 1) {
                    $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
                    $this->mergeRanges[] = "K{$dataStartRow}:K{$dataEndRow}";
                }
            }

            // ── BARIS KOSONG PEMISAH ─────────────────────────────
            $rows[] = ['', '', '', '', '', '', '', '', '', '', ''];
            $rowIndex++;
        }

        return $rows;
    }

    public function title(): string { return 'Hasil Stik'; }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F497D';
        $blueLight = '2E75B6';

        // ── MERGE CELL (Tanggal & TTL PKJ) ───────────────────────
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── STYLE PER BARIS ───────────────────────────────────────
        foreach ($this->styleMap as $rowNum => $type) {
            switch ($type) {

                case 'section_title':
                    $sheet->mergeCells("A{$rowNum}:K{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'size'  => 12,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueDark],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(22);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueLight],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'FFFFFF'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(18);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'BFBFBF'],
                            ],
                        ],
                    ]);
                    // Tanggal rata kiri (sebelum di-override merge jika ada)
                    $sheet->getStyle("A{$rowNum}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    break;
            }
        }

        // ── LEBAR KOLOM ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('I')->setWidth(8);
        $sheet->getColumnDimension('J')->setWidth(8);
        $sheet->getColumnDimension('K')->setWidth(10);

        return [];
    }
}