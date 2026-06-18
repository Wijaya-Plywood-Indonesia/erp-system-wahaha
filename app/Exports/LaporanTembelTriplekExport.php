<?php

namespace App\Exports;

use App\Models\BahanPenolongProduksi;
use App\Models\BahanPenolongTembeltriplek;
use App\Models\HargaPegawai;
use App\Models\HasilTembeltriplek;
use App\Models\ProduksiTembeltriplek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// =============================================================================
//  CLASS 1 — LaporanTembelTriplekExport
//  "Pintu masuk" — hanya mengarahkan ke sheet mana yang perlu dibuat.
// =============================================================================

class LaporanTembelTriplekExport implements WithMultipleSheets
{
    protected string $tanggal;
    protected array  $laporanRingkasan;

    public function __construct(string $tanggal, array $laporanRingkasan)
    {
        $this->tanggal          = $tanggal;
        $this->laporanRingkasan = $laporanRingkasan;

        Log::info('[LaporanTembelTriplekExport] Export dimulai', [
            'tanggal'        => $tanggal,
            'jumlah_pegawai' => count($laporanRingkasan),
        ]);
    }

    public function sheets(): array
    {
        return [
            new LaporanTembelTriplekSheet($this->tanggal, $this->laporanRingkasan),
            new OngkosTembelTriplekSheet($this->tanggal),
        ];
    }
}


// =============================================================================
//  CLASS 2 — LaporanTembelTriplekSheet  (sheet tab 1, tidak berubah)
// =============================================================================

class LaporanTembelTriplekSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected string $tanggal;
    protected array  $laporanRingkasan;
    protected int    $barisAwalDetail     = 0;
    protected int    $barisTotalRingkasan = 0;

    public function __construct(string $tanggal, array $laporanRingkasan)
    {
        $this->tanggal          = $tanggal;
        $this->laporanRingkasan = $laporanRingkasan;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->laporanRingkasan as $row) {
            $rows[] = [
                $row['kodep'],
                $row['nama'],
                $row['jam_masuk'],
                $row['jam_pulang'],
                $row['hasil'],
                $row['modal'],
                $row['total'],
                $row['selisih'],
                $row['kendala'],
            ];
        }

        $totalModal   = array_sum(array_column($this->laporanRingkasan, 'modal'));
        $totalHasil   = array_sum(array_column($this->laporanRingkasan, 'total'));
        $totalSelisih = array_sum(array_column($this->laporanRingkasan, 'selisih'));
        $rows[]       = ['', 'TOTAL', '', '', '', $totalModal, $totalHasil, $totalSelisih, ''];

        $this->barisTotalRingkasan = 3 + count($this->laporanRingkasan) + 1;

        $rows[] = ['', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', '', ''];
        $rows[] = ['DETAIL HASIL PER BARANG', '', '', '', '', '', '', '', ''];
        $rows[] = ['Barang', 'Modal (Pcs)', 'Hasil (Pcs)', 'Selisih (Pcs)', 'Pegawai Terlibat', 'Jumlah Orang', 'No. Palet', '', ''];

        $this->barisAwalDetail = count($rows) + 3;

        foreach ($this->ambilDetailHasil() as $d) {
            $rows[] = $d;
        }

        Log::info('[LaporanTembelTriplekSheet] Total baris disusun', [
            'total_baris'        => count($rows),
            'baris_detail_mulai' => $this->barisAwalDetail,
        ]);

        return $rows;
    }

    private function ambilDetailHasil(): array
    {
        $produksi = ProduksiTembeltriplek::with([
            'hasilTembeltriplek.barangSetengahJadi.jenisBarang',
            'hasilTembeltriplek.barangSetengahJadi.ukuran',
            'hasilTembeltriplek.barangSetengahJadi.grade',
            'hasilTembeltriplek.pegawais',
        ])
            ->whereDate('tanggal', $this->tanggal)
            ->get();

        $rows = [];
        foreach ($produksi as $p) {
            foreach ($p->hasilTembeltriplek as $h) {
                $namaBarang  = optional($h->barangSetengahJadi)->nama_lengkap ?? 'Tanpa Nama';
                $namaPegawai = $h->pegawais->pluck('nama')->filter()->implode(', ') ?: '-';
                $jumlahOrang = $h->pegawais->count();
                $rows[]      = [
                    $namaBarang,
                    $h->modal,
                    $h->hasil,
                    $h->hasil - $h->modal,
                    $namaPegawai,
                    $jumlahOrang,
                    $h->nomor_palet ?? '-',
                    '',
                    '',
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            ['Laporan Tembel Triplek - ' . Carbon::parse($this->tanggal)->format('d/m/Y')],
            [],
            ['Kodep', 'Nama Pegawai', 'Masuk', 'Pulang', 'Hasil/Barang', 'Modal (Pcs)', 'Hasil (Pcs)', 'Selisih (Pcs)', 'Kendala'],
        ];
    }

    public function title(): string
    {
        return 'Laporan Tembel Triplek';
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 25, 'C' => 10, 'D' => 10, 'E' => 35, 'F' => 14, 'G' => 14, 'H' => 14, 'I' => 25];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle("A{$this->barisTotalRingkasan}:I{$this->barisTotalRingkasan}")->getFont()->setBold(true);

        $barisJudulDetail = $this->barisAwalDetail - 1;
        $sheet->mergeCells("A{$barisJudulDetail}:G{$barisJudulDetail}");
        $sheet->getStyle("A{$barisJudulDetail}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$this->barisAwalDetail}:G{$this->barisAwalDetail}")->getFont()->setBold(true);
        $sheet->getStyle("A{$this->barisAwalDetail}:G{$this->barisAwalDetail}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        return [];
    }
}


// =============================================================================
//  CLASS 3 — OngkosTembelTriplekSheet  (sheet tab 2 — format persis Excel asli)
//
//  STRUKTUR EXCEL ASLI (per tanggal, 13 baris):
//  ┌────┬──────────────────────────┬────────┬────────┬────────┬───┬───┐
//  │ A  │ B                        │ C      │ D      │ E      │...│...│
//  ├────┼──────────────────────────┼────────┼────────┼────────┤   │   │
//  │Tgl │ Lem Aruki (kg)           │        │ HARGA  │ TOTAL  │   │   │
//  │    │ Lem Dover (kg)           │ jumlah │ HARGA  │=C*D    │   │   │
//  │    │ Pewarna (gr)             │        │ HARGA  │=C*D    │   │   │
//  │    │ AIR                      │        │ HARGA  │=C*D    │   │   │
//  │    │ HDR (gr)                 │        │ HARGA  │=C*D    │   │   │
//  │    │ TEPUNG BGS (kg)          │ jumlah │ HARGA  │=C*D    │   │   │
//  │    │ Tepung PJP/industri (kg) │        │ HARGA  │=C*D    │   │   │
//  │    │ Isi  Steples             │        │ HARGA  │=C*D    │   │   │
//  │    │ Solasi Putih             │        │ HARGA  │=C*D    │   │   │
//  │    │ Penyusutan               │ jumlah │ 635000 │=C*D    │   │   │
//  │    │ Bulanan                  │ jumlah │ 220000 │=C*D    │   │   │
//  │    │ Pekerja                  │ jumlah │ harga  │=C*D    │   │   │
//  │    │ TOTAL :                  │        │        │=SUM(E) │   │   │
//  └────┴──────────────────────────┴────────┴────────┴────────┴───┴───┘
//
//  KOLOM:
//   A = Tgl (merge ke bawah 13 baris)
//   B = Nama bahan
//   C = Jumlah (input dari DB / hardcoded)
//   D = Harga  — =VLOOKUP(B,Master!...) untuk bahan, hardcoded untuk penyusutan/pekerja
//   E = Total  — =C*D (rumus Excel)
//
//  PENJELASAN UNTUK JUNIOR:
//   Kita tidak perlu kolom F-O dari Excel asli (p, l, t, byk, jenis, kubikasi, dll)
//   karena itu data produksi harian yang berbeda. Sheet ongkos ini khusus biaya.
//   Yang kita tiru adalah: struktur baris, kolom A-E, format angka, warna merah
//   pada kolom D (HARGA), dan baris TOTAL dengan =SUM().
// =============================================================================

class OngkosTembelTriplekSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    protected string $tanggal;
    protected int $rowDataStart = 4; // Data dimulai dari baris ke-4
    protected int $rowTotalStart = 0;
    protected array $mergeRanges = [];
    protected int $maxRows = 0;

    public function __construct(string $tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function array(): array
    {
        $rows = [];

        // ── Baris 1: Title Block (A1:P1) ──
        $rows[] = ['Tembel Triplek', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // ── Baris 2: Header Kolom (A2:P2) ──
        $rows[] = [
            'Tgl',
            'BAHAN',
            'Jumlah',
            'HARGA',
            'TOTAL',
            '', // Kolom F (Spacer)
            'P',
            'L',
            'T',
            'Jenis',
            'Lembar',
            'Kubikasi',
            'Ongkos tanpa bahan',
            'Ongkos dgn bahan',
            'Harga/M3 dengan pekerja',
            'Harga/Lb Dengan Pekerja'
        ];

        // ── Baris 3: Rata-rata (A3:P3) ──
        // Formula dinamis untuk rata-rata disematkan di sini (nanti diupdate di akhir loop)
        $rows[] = [
            'Rata-rata',
            '',
            '',
            '',
            '',
            '', // Kolom F
            '',
            '',
            '',
            '',
            '',
            "=AVERAGE(L{$this->rowDataStart}:L" . ($this->rowDataStart + 100) . ")",
            '',
            '',
            '',
            ''
        ];

        // ── Baris 4+: Mulai Baris Data ──
        $blokStart = 4;

        $bahan = $this->ambilBahanPenolong();
        $jumlahPekerja = $this->hitungJumlahPekerja();
        $hargaPegawai  = HargaPegawai::latest()->value('harga') ?? 115000;
        $tanggalCell   = Carbon::parse($this->tanggal)->format('d/m/Y');

        $bahanList = [
            'Lem Aruki (kg)',
            'Lem Dover (kg)',
            'Pewarna (gr)',
            'AIR',
            'HDR (gr)',
            'TEPUNG BGS (kg)',
            'Tepung PJP/industri  (kg)',
            'Isi  Steples',
            'Solasi Putih',
        ];

        // Map jumlah bahan dari produksi tembel
        $jumlahMap = [];
        foreach ($bahan as $b) {
            $jumlahMap[$b['nama_bahan']] = $b['jumlah'];
        }

        // --- PRE-PROCESSING HARGA MASTER (OPTIMASI) ---
        // Alih-alih melakukan preg_replace berulang kali di dalam loop, kita siapkan kamus datanya di awal
        $hargaMasterMap = BahanPenolongProduksi::pluck('harga', 'nama_bahan_penolong')->toArray();
        $smartHargaMap = [];
        foreach ($hargaMasterMap as $dbName => $dbHarga) {
            // Simpan format lowercase penuh
            $smartHargaMap[strtolower(trim($dbName))] = $dbHarga;
            // Simpan format lowercase tanpa isi kurung (misal: "lem aruki (kg)" jadi "lem aruki")
            $cleanName = strtolower(trim(preg_replace('/\s*\([^)]*\)/', '', $dbName)));
            $smartHargaMap[$cleanName] = $dbHarga;
        }

        // Ambil data produksi (sisi kanan) yang sekarang dinamis dari database
        $produksiData = $this->ambilDataProduksi();

        // Kita hitung mana yang lebih panjang: baris bahan (12 baris) atau baris produksi?
        $totalBarisBahan = 12; // 9 bahan + penyusutan + bulanan + pekerja
        $this->maxRows = max($totalBarisBahan, count($produksiData));
        $this->rowTotalStart = $blokStart + $this->maxRows;

        // LOOPING BERSAMA (Kiri dan Kanan Sejajar)
        for ($i = 0; $i < $this->maxRows; $i++) {
            $currentRow = $blokStart + $i;

            // --- PERSIAPAN DATA KIRI (KOLOM A-E) ---
            if ($i < 9) { // 9 Bahan Pertama
                $namaBahan = $bahanList[$i];
                $colB = $namaBahan;

                // Gunakan blank ('') jika jumlah 0 agar Excel rapi seperti gambar
                $colC = !empty($jumlahMap[$namaBahan]) ? $jumlahMap[$namaBahan] : '';

                // --- PENCOCOKAN HARGA PINTAR YANG SUDAH DIOPTIMASI ---
                $harga = $hargaMasterMap[$namaBahan] ?? null; // Coba exact match dulu

                if ($harga === null) {
                    $searchName = strtolower(trim($namaBahan));
                    $harga = $smartHargaMap[$searchName] ?? null; // Coba case-insensitive match

                    if ($harga === null) {
                        // Coba pencocokan tanpa teks dalam kurung
                        $cleanSearchName = strtolower(trim(preg_replace('/\s*\([^)]*\)/', '', $namaBahan)));
                        $harga = $smartHargaMap[$cleanSearchName] ?? null;
                    }
                }

                $colD = $harga ?: '';
                $colE = ($colC !== '' && $colD !== '') ? "=IFERROR(C{$currentRow}*D{$currentRow}, 0)" : '';
            } elseif ($i == 9) { // Baris 10: Penyusutan
                $colB = 'Penyusutan';
                $colC = '';
                $colD = '';
                $colE = '';
            } elseif ($i == 10) { // Baris 11: Bulanan
                $colB = 'Bulanan';
                $colC = '';
                $colD = '';
                $colE = '';
            } elseif ($i == 11) { // Baris 12: Pekerja
                $colB = 'Pekerja';
                $colC = $jumlahPekerja ?: '';
                $colD = $hargaPegawai;
                $colE = ($colC !== '') ? "=IFERROR(C{$currentRow}*D{$currentRow}, 0)" : '';
            } else {
                // Sisa baris kosong jika data produksi lebih dari 12
                $colB = '';
                $colC = '';
                $colD = '';
                $colE = '';
            }

            $colA = ($i === 0) ? $tanggalCell : '';

            // --- PERSIAPAN DATA KANAN (KOLOM G-L) ---
            if (isset($produksiData[$i])) {
                $prod = $produksiData[$i];
                $colG = $prod['p'];
                $colH = $prod['l'];
                $colI = $prod['t'];
                $colJ = $prod['jenis'];
                $colK = $prod['lembar'];
                $colL = $prod['kubikasi'];
            } else {
                $colG = '';
                $colH = '';
                $colI = '';
                $colJ = '';
                $colK = '';
                $colL = '';
            }

            // --- RUMUS REKAP (KOLOM M-P) ---
            // Menggunakan `""` pada parameter IFERROR agar bersih dari #DIV/0! jika kubikasi belum ada
            if ($i === 0) {
                $rowPekerja = $blokStart + 11;
                $rowBahanEnd = $blokStart + 8;
                $rowBulanan = $blokStart + 10;
                $rowTotal = $this->rowTotalStart;

                $colM = "=IFERROR(E{$rowPekerja} / L{$rowTotal}, \"\")";
                $colN = "=IFERROR((SUM(E{$blokStart}:E{$rowBahanEnd}) + E{$rowBulanan}) / L{$rowTotal}, \"\")";
                $colO = "=IFERROR(E{$rowTotal} / L{$rowTotal}, \"\")";
                $colP = "=IFERROR(E{$rowTotal} / K{$rowTotal}, \"\")";
            } else {
                $colM = '';
                $colN = '';
                $colO = '';
                $colP = '';
            }

            // --- GABUNGKAN KE BARIS ---
            $rows[] = [
                $colA,
                $colB,
                $colC,
                $colD,
                $colE,
                '', // Kolom F kosong
                $colG,
                $colH,
                $colI,
                $colJ,
                $colK,
                $colL,
                $colM,
                $colN,
                $colO,
                $colP
            ];
        }

        // ── Baris TOTAL ──
        $endSumRow = max($blokStart, $this->rowTotalStart - 1);

        $rows[] = [
            '', // A
            'TOTAL :', // B
            '', // C
            '', // D
            "=SUM(E{$blokStart}:E{$endSumRow})", // E
            '', // F (Spacer)
            '',
            '',
            '',
            '', // G, H, I, J
            "=SUM(K{$blokStart}:K{$endSumRow})", // K (Sum Lembar)
            "=SUM(L{$blokStart}:L{$endSumRow})", // L (Sum Kubikasi)
            '', // M
            '', // N
            '', // O
            '', // P
        ];

        // ── CATAT RANGE MERGE ──
        $this->mergeRanges[] = "A{$blokStart}:A{$endSumRow}";
        $this->mergeRanges[] = "G{$this->rowTotalStart}:J{$this->rowTotalStart}";

        // Merge vertikal kolom Rumus Rekap
        $this->mergeRanges[] = "M{$blokStart}:M{$endSumRow}";
        $this->mergeRanges[] = "N{$blokStart}:N{$endSumRow}";
        $this->mergeRanges[] = "O{$blokStart}:O{$endSumRow}";
        $this->mergeRanges[] = "P{$blokStart}:P{$endSumRow}";

        // Update Average dengan referensi baris yang dinamis & presisi
        $rows[2][11] = "=IFERROR(AVERAGE(L{$blokStart}:L{$endSumRow}), \"\")";

        return $rows;
    }

    // =========================================================
    //  QUERY HELPERS
    // =========================================================

    private function ambilBahanPenolong(): array
    {
        $produksiIds = ProduksiTembeltriplek::whereDate('tanggal', $this->tanggal)->pluck('id');
        $bahans = BahanPenolongTembeltriplek::whereIn('id_produksi_tembel_triplek', $produksiIds)->get();

        $result = [];
        foreach ($bahans as $b) {
            $result[] = [
                'nama_bahan' => $b->nama_bahan,
                'jumlah'     => $b->jumlah ?? 0,
            ];
        }
        return $result;
    }

    private function hitungJumlahPekerja(): int
    {
        return ProduksiTembeltriplek::with('pegawaiTembeltriplek')
            ->whereDate('tanggal', $this->tanggal)
            ->get()
            ->flatMap(fn($p) => $p->pegawaiTembeltriplek ? $p->pegawaiTembeltriplek->pluck('id_pegawai') : [])
            ->filter()
            ->unique()
            ->count();
    }

    private function ambilDataProduksi(): array
    {
        $produksiIds = ProduksiTembeltriplek::whereDate('tanggal', $this->tanggal)->pluck('id');

        $hasilTembel = HasilTembeltriplek::with([
            'barangSetengahJadi.ukuran',
            'barangSetengahJadi.grade'
        ])
            ->whereIn('id_produksi_tembel_triplek', $produksiIds)
            ->get();

        $result = [];

        foreach ($hasilTembel as $hasil) {
            $barang = $hasil->barangSetengahJadi;
            $ukuran = $barang ? $barang->ukuran : null;
            $grade  = $barang ? $barang->grade : null;

            $p = $ukuran ? (float) $ukuran->panjang : 0;
            $l = $ukuran ? (float) $ukuran->lebar : 0;
            $t = $ukuran ? (float) $ukuran->tebal : 0;

            $jenis = $grade ? $grade->nama_grade : '-';
            $jumlahLembar = (float) $hasil->hasil;

            $kubikasi = ($p * $l * $t / 10000000) * $jumlahLembar;

            $result[] = [
                'p' => $p ?: '',
                'l' => $l ?: '',
                't' => $t ?: '',
                'jenis' => $jenis,
                'lembar' => $jumlahLembar,
                'kubikasi' => round($kubikasi, 4),
            ];
        }

        return $result;
    }

    // =========================================================
    //  STYLING & FORMATTING
    // =========================================================

    public function title(): string
    {
        return 'ongkos';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Tgl
            'B' => 25,  // BAHAN
            'C' => 12,  // Jumlah
            'D' => 15,  // HARGA
            'E' => 18,  // TOTAL
            'F' => 3,   // SPACER / JEDA
            'G' => 8,   // P
            'H' => 8,   // L
            'I' => 8,   // T
            'J' => 12,  // Jenis
            'K' => 10,  // Lembar
            'L' => 15,  // Kubikasi
            'M' => 20,  // Ongkos tanpa bahan
            'N' => 20,  // Ongkos dgn bahan
            'O' => 22,  // Harga/M3
            'P' => 22,  // Harga/Lb
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $thinBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        // ── Style Baris 1 (Judul) ──
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // ── Style Baris 2 (Header Kolom) ──
        $sheet->getStyle('A2:E2')->applyFromArray($thinBorder);
        $sheet->getStyle('G2:P2')->applyFromArray($thinBorder);
        $sheet->getStyle('A2:P2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6E6E6']],
        ]);
        $sheet->getStyle('F2')->getFill()->setFillType(Fill::FILL_NONE);
        $sheet->getRowDimension(2)->setRowHeight(35);

        // ── Style Baris 3 (Rata-rata) ──
        $sheet->getStyle('A3:E3')->applyFromArray($thinBorder);
        $sheet->getStyle('G3:P3')->applyFromArray($thinBorder);
        $sheet->getStyle('A3:P3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
        ]);
        $sheet->getStyle('F3')->getFill()->setFillType(Fill::FILL_NONE);
        $sheet->getStyle('L3')->getNumberFormat()->setFormatCode('#,##0.0000');

        // ── Style Data Loop ──
        $endDataRow = max($this->rowDataStart, $this->rowTotalStart - 1);
        for ($r = $this->rowDataStart; $r <= $endDataRow; $r++) {
            // Border
            $sheet->getStyle("A{$r}:E{$r}")->applyFromArray($thinBorder);
            $sheet->getStyle("G{$r}:P{$r}")->applyFromArray($thinBorder);

            // Alignments Kiri
            $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$r}:E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Harga & Total
            $sheet->getStyle("D{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0');

            // Alignments Kanan & Format
            $sheet->getStyle("G{$r}:J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("K{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$r}")->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getStyle("L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$r}")->getNumberFormat()->setFormatCode('#,##0.0000');

            $sheet->getStyle("M{$r}:P{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("M{$r}:P{$r}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // Merge Custom
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // ── Style Baris TOTAL ──
        $totalRow = $this->rowTotalStart;
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray($thinBorder);
        $sheet->getStyle("G{$totalRow}:P{$totalRow}")->applyFromArray($thinBorder);

        $sheet->getStyle("B{$totalRow}:E{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
        ]);

        $sheet->getStyle("B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("K{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("L{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');

        $sheet->getStyle("G{$totalRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        ]);

        return [];
    }
}
