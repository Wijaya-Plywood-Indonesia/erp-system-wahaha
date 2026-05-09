<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class LaporanGrajiTriplekExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    /**
     * MASTER MAPPING GRADE:
     * Menentukan kolom apa saja yang akan muncul di Excel.
     * Nama di sini harus sesuai dengan kata terakhir setelah tanda "|" pada nama_grade.
     */
    private const MASTER_GRADE = ['better', 'aj', 'better local', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    /**
     * Mengolah data mentah menjadi format summary per baris.
     */
    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d M');

            foreach ($produksi->hasilGrajiTriplek as $hasil) {
                $barang = $hasil->barangSetengahJadiHp;
                if (!$barang) continue;

                // Mengambil data dimensi dan jenis
                $ukuran = $barang->ukuran;
                $p = (float) ($ukuran->panjang ?? 0);
                $l = (float) ($ukuran->lebar   ?? 0);
                $t = (float) ($ukuran->tebal   ?? 0);
                $jenis = strtoupper($barang->jenisBarang->nama_jenis ?? '-');

                /**
                 * LOGIKA PARSING GRADE:
                 * Mengambil "BETTER" dari "Plywood | BETTER"
                 */
                $rawGrade = $barang->grade->nama_grade ?? '';
                $gradeParts = explode('|', $rawGrade);
                $gradeName = strtolower(trim(end($gradeParts)));

                /**
                 * LOGIKA ADMINISTRASI:
                 * Key menyertakan $gradeName agar Ukuran sama tapi Grade beda muncul di baris baru.
                 */
                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$gradeName}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'pekerja_ids' => [],
                    ];

                    // Inisialisasi kolom Grade dengan angka 0
                    foreach (self::MASTER_GRADE as $g) {
                        $this->summary[$key]['grade_' . $g] = 0;
                    }
                }

                // Isi nilai jumlah (isi) ke kolom grade yang sesuai
                if (in_array($gradeName, self::MASTER_GRADE)) {
                    $this->summary[$key]['grade_' . $gradeName] += (int) $hasil->isi;
                }

                // Ambil ID pekerja dari relasi produksi induk (Pegawai Graji Triplek)
                foreach ($produksi->pegawaiGrajiTriplek as $pg) {
                    $this->summary[$key]['pekerja_ids'][] = $pg->id_pegawai;
                }
            }
        }

        // Urutkan data berdasarkan Jenis Kayu (Key awal)
        ksort($this->summary);
    }

    /**
     * Menyusun koleksi data untuk dicetak ke baris-baris Excel.
     */
    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalGrade = count(self::MASTER_GRADE);
        $lastRow = $dataStart + count($this->summary) - 1;

        // --- ROW 2: GRAND TOTAL (Kuning) ---
        $grandRow = ['', '', '', '', '']; // Kolom A - E
        for ($i = 0; $i < $totalGrade; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        // Kolom TTL PKJ (Total Pekerja)
        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalGrade);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";
        $rows->push($grandRow);

        // --- ROW 3+: DATA ROWS ---
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            // Masukkan nilai grade sesuai urutan MASTER_GRADE
            foreach (self::MASTER_GRADE as $g) {
                $val = $s['grade_' . $g] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            // Hitung pekerja unik untuk baris ini
            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';

            $rows->push($row);
        }

        return $rows;
    }

    /**
     * Menentukan Judul Kolom (Header).
     */
    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_GRADE as $g) {
            $heads[] = 'Grade ' . strtoupper($g);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    /**
     * Mengatur Styling Excel (Warna, Garis, Alignment).
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Baris 1 (Biru) & Baris 2 (Kuning)
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['ARGB' => $color]
                        ],
                        'font' => ['bold' => true],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER
                        ],
                    ]);
                }

                // Style Body Data (Mulai Baris 3)
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER
                        ],
                    ]);
                }

                // Auto-size kolom agar lebar sel pas
                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Graji Triplek';
    }
}
