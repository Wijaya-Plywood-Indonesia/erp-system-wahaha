<?php

namespace App\Exports;

use App\Models\ProduksiPotSiku;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPotSikuExport implements WithMultipleSheets
{
    protected $data;
    protected $tanggal;

    public function __construct($data, $tanggal = null)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotSikuDetailSheet($this->data),
            new LaporanPotSikuRekapSheet($this->tanggal),
        ];
    }
}

class LaporanPotSikuDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $data;
    protected $mergeRows = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $exportData = collect();
        $currentRow = 2;

        foreach ($this->data as $laporan) {
            foreach ($laporan['pekerja_list'] as $pekerja) {
                $fixedTarget = 300;
                $rowCount = count($pekerja['detail_barang']);

                if ($rowCount > 1) {
                    $this->mergeRows[] = [
                        'start' => $currentRow,
                        'end' => $currentRow + $rowCount - 1
                    ];
                }

                foreach ($pekerja['detail_barang'] as $detail) {
                    $exportData->push([
                        'tanggal' => $laporan['tanggal'],
                        'kode_pegawai' => $pekerja['kode_pegawai'],
                        'nama_pegawai' => $pekerja['nama_pegawai'],
                        'jam_masuk' => $pekerja['jam_masuk'],
                        'jam_pulang' => $pekerja['jam_pulang'],
                        'target' => $fixedTarget,
                        'jenis_kayu' => $detail['jenis_kayu'],
                        'ukuran' => $detail['ukuran'],
                        'kw' => $detail['kw'],
                        'tinggi' => $detail['tinggi'],
                        'hasil_total' => $pekerja['hasil'],
                        'potongan' => $pekerja['potongan_target'],
                        'keterangan' => $pekerja['ket'],
                    ]);
                }
                $currentRow += $rowCount;
            }
        }
        return $exportData;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Kode', 'Nama Pegawai', 'Masuk', 'Pulang', 'Target', 'Jenis Kayu', 'Ukuran', 'KW', 'Hasil (Tinggi)', 'Total Hasil', 'Potongan Target', 'Keterangan'
        ];
    }

    public function map($row): array
    {
        return [
            $row['tanggal'], $row['kode_pegawai'], $row['nama_pegawai'], $row['jam_masuk'], $row['jam_pulang'], 300, $row['jenis_kayu'], $row['ukuran'], $row['kw'], $row['tinggi'], $row['hasil_total'], $row['potongan'], $row['keterangan'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($this->mergeRows as $range) {
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'K', 'L', 'M'] as $col) {
                $sheet->mergeCells("{$col}{$range['start']}:{$col}{$range['end']}");
                $sheet->getStyle("{$col}{$range['start']}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
        }

        $sheet->getStyle('L2:L' . $sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function title(): string
    {
        return 'Detail Per Pekerja';
    }
}

class LaporanPotSikuRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;
    protected $mergeData = [];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $produksis = ProduksiPotSiku::with([
            'detailBarangDikerjakanPotSiku.ukuran',
            'detailBarangDikerjakanPotSiku.jenisKayu',
            'pegawaiPotSiku'
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $rows = collect();
        $currentRow = 2;
        $this->mergeData = [];

        foreach ($produksis as $index => $produksi) {
            $tgl = Carbon::parse($produksi->tanggal_produksi)->format('d-M');
            $totalPekerja = $produksi->pegawaiPotSiku()->whereNotNull('id_pegawai')->distinct('id_pegawai')->count('id_pegawai');
            
            $details = $produksi->detailBarangDikerjakanPotSiku->groupBy(function($item) {
                $u = $item->ukuran;
                $jRaw = strtolower($item->jenisKayu->nama_kayu ?? '');
                $jenis = $item->jenisKayu->kode_kayu ?? '-';
                if (str_contains($jRaw, 'sengon')) $jenis = 'afs';
                elseif (str_contains($jRaw, 'jabon')) $jenis = 'afj';

                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $jenis;
            });

            if ($details->count() > 0) {
                $this->mergeData[] = ['start' => $currentRow, 'end' => $currentRow + $details->count() - 1];

                foreach ($details as $key => $items) {
                    [$p, $l, $t, $jenis] = explode('|', $key);
                    $p = str_replace('.', ',', (string)(float)$p);
                    $l = str_replace('.', ',', (string)(float)$l);
                    $t = str_replace('.', ',', (string)(float)$t);
                    $byk = $items->sum('tinggi');

                    $rows->push([
                        'tanggal' => $tgl,
                        'p' => $p,
                        'l' => $l,
                        't' => $t,
                        'jenis' => $jenis,
                        'byk' => $byk ?: '',
                        'ttl_pkj' => $totalPekerja,
                    ]);
                    $currentRow++;
                }

                if ($index < $produksis->count() - 1) {
                    $rows->push(['', '', '', '', '', '', '']);
                    $currentRow++;
                }
            }
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'p', 'l', 't', 'jenis', 'byk', 'TTL PKJ'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'name' => 'Times New Roman'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $peachColor = 'F9CB9C';
                $sheet->getStyle('A1:G1')->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font' => ['bold' => true, 'size' => 12],
                ]);

                foreach ($this->mergeData as $range) {
                    $start = $range['start']; $end = $range['end'];
                    if ($start < $end) {
                        $sheet->mergeCells("A{$start}:A{$end}");
                        $sheet->mergeCells("G{$start}:G{$end}");
                    }
                    $sheet->getStyle("A{$start}:G{$end}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'font' => ['name' => 'Times New Roman'],
                    ]);
                    $sheet->getStyle("A{$start}:A{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("E{$start}:E{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("G{$start}:G{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("A{$start}:A{$end}")->getFont()->setBold(true);
                    $sheet->getStyle("G{$start}:G{$end}")->getFont()->setBold(true);
                }
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(8);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(12);
            },
        ];
    }

    public function title(): string
    {
        return 'Rekap Produksi Custom';
    }
}
