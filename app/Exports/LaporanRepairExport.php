<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanRepairExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // Array hasil RepairDataMap (untuk Sheet 1)
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query langsung ke DB, tidak lewat transformer!
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (UPDATE: TAMBAH KOLOM KETERANGAN)
// ============================================================
class LaporanRepairDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $detailData)
    {
        $this->data = collect($detailData)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->data as $groupKey => $items) {
            $first        = $items->first();
            $targetPerJam = $first['jam_kerja'] > 0
                ? round($first['target'] / $first['jam_kerja'], 2)
                : 0;
            $pekerja      = $first['pekerja'] ?? [];

            $rows->push(['MEJA',        $first['nomor_meja']]);
            $rows->push(['UKURAN',      $first['ukuran']]);
            $rows->push(['JENIS KAYU',  $first['jenis_kayu']]);
            $rows->push(['KW',          $first['kw']]);
            $rows->push(['TANGGAL',     $first['tanggal']]);
            $rows->push([]);

            // 🚀 UPDATE HEADER TABEL: Menambahkan Keterangan Hasil & Kerja di samping Keterangan Absen lama
            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil', // 👈 Kolom Baru
                'Keterangan Kerja', // 👈 Kolom Baru
                '',
                'Target Harian',
                'Jam Kerja',
                'Target / Jam',
                'Hasil',
                'Selisih'
            ]);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',       // Ini Keterangan Absen bawaan array Anda
                    $p['keterangan_hasil'] ?? '—', // 👈 Diambil langsung dari mapping data hasil pekerja
                    $p['keterangan_kerja'] ?? '—', // 👈 Diambil langsung dari mapping data rencana kerja pekerja
                    '',
                    $first['target'],
                    $first['jam_kerja'],
                    $targetPerJam,
                    $first['hasil'],
                    $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                '',
                '',
                '',
                '',
                $totalPotongan,
                '',
                '', // Kosongkan kolom baru untuk baris TOTAL
                '', // Kosongkan kolom baru untuk baris TOTAL
                '',
                $first['target'],
                $first['jam_kerja'],
                $targetPerJam,
                $first['hasil'],
                $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }
    public function title(): string
    {
        return 'Detail Per Meja';
    }
}

// ============================================================
// SHEET 2: SUMMARY — Bersih Seperti Semula
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    private const MASTER_KW = ['1', '2', '3', '4', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d M');

            foreach ($produksi->modalRepairs as $modal) {
                $p = (float) ($modal->ukuran->panjang ?? 0);
                $l = (float) ($modal->ukuran->lebar   ?? 0);
                $t = (float) ($modal->ukuran->tebal   ?? 0);
                $jenis = strtoupper($modal->jenisKayu->kode_kayu ?? substr($modal->jenisKayu->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim($modal->kw ?? ''));

                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$kwData}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'current_kw'  => $kwData,
                        'pekerja_ids' => [],
                    ];

                    foreach (self::MASTER_KW as $mKw) {
                        $this->summary[$key]['kw_' . $mKw] = 0;
                    }
                }

                $hasilModal = 0;
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu > 0) {
                        $hasilModal += $hasilIndividu;
                        $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                    }
                }

                if ($kwData !== '' && $hasilModal > 0) {
                    if (in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $hasilModal;
                    }
                }
            }
        }

        ksort($this->summary);
    }

    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalMasterKw = count(self::MASTER_KW);
        $lastRow = $dataStart + count($this->summary) - 1;

        // Row 2: Grand Total
        $grandRow = ['', '', '', '', ''];
        for ($i = 0; $i < $totalMasterKw; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalMasterKw);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";

        $rows->push($grandRow);

        // Row 3+: Data Rows
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            foreach (self::MASTER_KW as $mKw) {
                $val = $s['kw_' . $mKw] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_KW as $mKw) {
            $heads[] = 'KW ' . strtoupper($mKw);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Header & Grand Total
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => $color]],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }
}

// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
// ============================================================
// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'Jurnal';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 8,
            'F' => 8,
            'G' => 15,
            'H' => 45,
            'I' => 8,
            'J' => 8,
            'K' => 14,
            'L' => 16,
            'M' => 16,
            'N' => 22,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '0.00',
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false, string $status = 'jadi'): int
    {
        $jns = $this->normalizeJenis($jenis);

        if ($isAf) {
            $kelompok = $tebal < 1 ? 'ppc_faceback' : 'ppc_core';
            $harga = [
                'sengon'  => [
                    'ppc_faceback' => ['basah' => 1700000, 'kering' => 1700000, 'jadi' => 1700000],
                    'ppc_core'     => ['basah' => 1500000, 'kering' => 1500000, 'jadi' => 1500000]
                ],
                'meranti' => [
                    'ppc_faceback' => ['basah' => 2000000, 'kering' => 2000000, 'jadi' => 2000000],
                    'ppc_core'     => ['basah' => 1800000, 'kering' => 1800000, 'jadi' => 1800000]
                ],
            ];
            return $harga[$jns][$kelompok][$status] ?? 0;
        }

        $kelompok = $tebal < 1 ? 'faceback' : 'core';
        $harga = [
            'sengon'  => [
                'faceback' => ['basah' => 2700000, 'kering' => 2800000, 'jadi' => 4000000],
                'core'     => ['basah' => 1700000, 'kering' => 1900000, 'jadi' => 2250000],
            ],
            'meranti' => [
                'faceback' => ['basah' => 8000000, 'kering' => 8500000, 'jadi' => 12500000], // face — konfirmasi dulu
                'core'     => ['basah' => 2100000, 'kering' => 2500000, 'jadi' => 2800000],
            ],
        ];
        return $harga[$jns][$kelompok][$status] ?? 0;
    }
    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,
            (string) $tgl,
            '',
            (string) $noAkun,
            '',
            '',
            'tembel',
            $keterangan,
            strtolower($map),
            strtolower($hitKbk),
            (float) $banyak,
            (float) $m3,
            (float) $harga,
            (float) $total,
        ];
    }

    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat         = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit        = 0;
            $totalKredit       = 0;
            $jurnalBlockDebit  = [];
            $jurnalBlockKredit = [];

            // Akumulator global
            $accumulatedHasilM3     = 0;
            $accumulatedModalM3     = 0;
            $accumulatedHasilBanyak = 0;
            $accumulatedModalBanyak = 0;

            // Akumulator per kelompok (jenis|core/faceback)
            $selisihPerGroup = [];

            // ============================================================
            // 1. DEBIT: Hasil Repair
            // ============================================================
            $groupedHasil = collect($produksi->hasilRepairs)->groupBy(function ($hasil) {
                $modal = $hasil->rencanaRepair?->modalRepairs;
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) {
                    return 'invalid_data';
                }
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower(($hasil->rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $kw       = (int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT); // ← tambah ini
                $kwNorm   = ($kw == 1 || $kw == 2) ? 'jadi' : 'kering';             // ← tambah ini
                return "{$jnsNorm}|{$modal->ukuran->panjang}|{$modal->ukuran->lebar}|{$modal->ukuran->tebal}|{$isAf}|{$kwNorm}"; // ← tambah |{$kwNorm}
            });

            foreach ($groupedHasil as $key => $items) {
                if ($key === 'invalid_data') continue;

                $firstHasil   = $items->first();
                $modal        = $firstHasil->rencanaRepair->modalRepairs;
                $namaKayuAsli = $modal->jenisKayu->nama_kayu ?? '';

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwNorm] = explode('|', $key);
                $isAf    = ($statusKw === 'af');
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;

                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                // Akumulasi global
                $accumulatedHasilM3     += $totalM3;
                $accumulatedHasilBanyak += $totalBanyak;

                // Akumulasi per kelompok
                $kelompok = $tebal < 1 ? 'faceback' : 'core';
                $groupKey = "{$jnsNorm}|{$kelompok}";
                if (!isset($selisihPerGroup[$groupKey])) {
                    $selisihPerGroup[$groupKey] = [
                        'hasilM3'     => 0,
                        'modalM3'     => 0,
                        'hasilBanyak' => 0,
                        'modalBanyak' => 0,
                        'jenis'       => $jnsNorm,
                        'tebal'       => $tebal,
                        'panjang'     => $panjang,
                        'lebar'       => $lebar,
                        'kelompok'    => $kelompok,
                        'isAf'        => $isAf,
                        'kwNorm'      => $kwNorm,
                    ];
                }
                $selisihPerGroup[$groupKey]['hasilM3']     += $totalM3;
                $selisihPerGroup[$groupKey]['hasilBanyak'] += $totalBanyak;
                $selisihPerGroup[$groupKey]['kwNorm']       = $kwNorm;

                $tipeVeneer = $tebal < 1 ? '260 face/back' : '130 core';
                $isSengon   = $jnsNorm === 'sengon';
                if ($isAf) {
                    $noAkun   = '1472.00';
                    $namaAkun = "Veneer Jadi ppc " . ucfirst($jnsNorm) . " WJY";
                } elseif ($kwNorm === 'jadi') {
                    $noAkun   = $isSengon ? '1466.00' : '1467.00';
                    $namaAkun = "Veneer Jadi {$tipeVeneer} " . ucfirst($jnsNorm) . " WJY";
                } else {
                    $noAkun   = $isSengon ? '1441.00' : '1447.00';
                    $namaAkun = "Veneer Kering {$tipeVeneer} " . ucfirst($jnsNorm) . " WJY";
                }
                $keterangan = $isAf
                    ? "af " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}"
                    : "{$tipeVeneer} " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}";

                $hargaPatok = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'jadi' : $kwNorm);
                $totalValue = $totalM3 * $hargaPatok;

                $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $totalBanyak, $totalM3, $hargaPatok, $totalValue, 'm');
                $totalDebit        += $totalValue;
            }

            // ============================================================
            // 2. KREDIT: Modal Repair
            // ============================================================
            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) {
                    return 'invalid_data';
                }
                $jnsNorm      = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus     = strtolower($modal->kw ?? '');
                $isAf         = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $isKehilangan = str_contains(strtolower($modal->keterangan ?? ''), 'kehilangan') ? 'hilang' : 'normal';
                $kw           = (int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT); // ← tambah
                $kwNorm       = ($kw == 1 || $kw == 2) ? 'jadi' : 'kering';             // ← tambah
                return "{$jnsNorm}|{$modal->ukuran->panjang}|{$modal->ukuran->lebar}|{$modal->ukuran->tebal}|{$isAf}|{$isKehilangan}|{$kwNorm}"; // ← tambah |{$kwNorm}
            });

            foreach ($groupedModal as $key => $items) {
                if ($key === 'invalid_data') continue;

                $firstModal   = $items->first();
                $namaKayuAsli = $firstModal->jenisKayu->nama_kayu ?? '';

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $statusHilang, $kwNorm] = explode('|', $key);
                $isAf          = ($statusKw === 'af');
                $hasKehilangan = ($statusHilang === 'hilang');
                $panjang       = (float) $panjang;
                $lebar         = (float) $lebar;
                $tebal         = (float) $tebal;

                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                // Akumulasi global
                $accumulatedModalM3     += $totalM3;
                $accumulatedModalBanyak += $totalBanyak;

                // Akumulasi per kelompok
                $kelompok = $tebal < 1 ? 'faceback' : 'core';
                $groupKey = "{$jnsNorm}|{$kelompok}";
                if (!isset($selisihPerGroup[$groupKey])) {
                    $selisihPerGroup[$groupKey] = [
                        'hasilM3'     => 0,
                        'modalM3'     => 0,
                        'hasilBanyak' => 0,
                        'modalBanyak' => 0,
                        'jenis'       => $jnsNorm,
                        'tebal'       => $tebal,
                        'panjang'     => $panjang, // ✅ fix: selalu disimpan
                        'lebar'       => $lebar,   // ✅ fix: selalu disimpan
                        'kelompok'    => $kelompok,
                        'isAf'        => $isAf,
                        'kwNorm'      => $kwNorm,
                    ];
                }
                $selisihPerGroup[$groupKey]['modalM3']     += $totalM3;
                $selisihPerGroup[$groupKey]['modalBanyak'] += $totalBanyak;
                $selisihPerGroup[$groupKey]['kwNorm']       = $kwNorm;

                $tipeVeneer = $tebal < 1 ? '260 face/back' : '130 core';
                $isSengon   = $jnsNorm === 'sengon';
                if ($isAf) {
                    $noAkun   = '1472.00';
                    $namaAkun = "Veneer Jadi ppc " . ucfirst($jnsNorm) . " WJY";
                } elseif ($kwNorm === 'jadi') {
                    $noAkun   = $isSengon ? '1466.00' : '1467.00';
                    $namaAkun = "Veneer Jadi {$tipeVeneer} " . ucfirst($jnsNorm) . " WJY";
                } else {
                    $noAkun   = $isSengon ? '1441.00' : '1447.00';
                    $namaAkun = "Veneer Kering {$tipeVeneer} " . ucfirst($jnsNorm) . " WJY";
                }
                $keterangan = $isAf
                    ? "af " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}"
                    : "{$tipeVeneer} " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}";

                if ($hasKehilangan) {
                    $keterangan .= " // kehilangan";
                }

                $hargaPatok = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'kering' : $kwNorm);
                $totalValue = $totalM3 * $hargaPatok;

                $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $totalBanyak, $totalM3, $hargaPatok, $totalValue, 'm');
                $totalKredit        += $totalValue;
            }

            // ============================================================
            // 3. LOGIKA JURNAL PENYEIMBANG SELISIH — PER KELOMPOK
            // ============================================================
            foreach ($selisihPerGroup as $g) {
                $diffM3     = round($g['hasilM3'] - $g['modalM3'], 4);
                $diffBanyak = $g['hasilBanyak'] - $g['modalBanyak'];

                if (round($diffM3, 4) == 0) continue;

                $isFaceBack = $g['kelompok'] === 'faceback';
                $isSengon   = $g['jenis'] === 'sengon';
                $tipeVeneer = $isFaceBack ? '260 face/back' : '130 core';

                if ($diffM3 < 0) {
                    $kwNorm       = $g['kwNorm'] ?? 'kering'; // ← tambah ini
                    $hargaPatok   = $this->getHargaPatok($g['jenis'], $g['tebal'], $g['isAf'], $kwNorm);
                    $valueSelisih = abs($diffM3) * $hargaPatok;

                    if ($g['isAf']) {
                        $noAkun   = '1472.00';
                        $namaAkun = "Veneer Jadi ppc " . ucfirst($g['jenis']) . " WJY";
                    } elseif ($kwNorm === 'jadi') {
                        $noAkun   = $isSengon ? '1466.00' : '1467.00';
                        $namaAkun = "Veneer Jadi {$tipeVeneer} " . ucfirst($g['jenis']) . " WJY";
                    } else {
                        $noAkun   = $isSengon ? '1441.00' : '1447.00';
                        $namaAkun = "Veneer Kering {$tipeVeneer} " . ucfirst($g['jenis']) . " WJY";
                    }

                    $keterangan = "Kekurangan {$tipeVeneer} " . $g['jenis'] . " uk {$g['panjang']} x {$g['lebar']} x {$g['tebal']}";

                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', abs($diffBanyak), abs($diffM3), $hargaPatok, $valueSelisih, 'm');
                    $totalKredit        += $valueSelisih;
                } else {
                    // 🟢 KELEBIHAN → DEBIT → ikut kwNorm
                    $kwNorm       = $g['kwNorm'] ?? 'jadi'; // ← tambah ini
                    $hargaPatok   = $this->getHargaPatok($g['jenis'], $g['tebal'], $g['isAf'], $kwNorm);
                    $valueSelisih = abs($diffM3) * $hargaPatok;
                    if ($g['isAf']) {
                        $noAkun   = '1472.00';
                        $namaAkun = "Veneer Jadi ppc " . ucfirst($g['jenis']) . " WJY";
                    } elseif ($kwNorm === 'jadi') {
                        $noAkun   = $isSengon ? '1466.00' : '1467.00';
                        $namaAkun = "Veneer Jadi {$tipeVeneer} " . ucfirst($g['jenis']) . " WJY";
                    } else {
                        $noAkun   = $isSengon ? '1441.00' : '1447.00';
                        $namaAkun = "Veneer Kering {$tipeVeneer} " . ucfirst($g['jenis']) . " WJY";
                    }
                    $keterangan = "Kelebihan {$tipeVeneer} " . $g['jenis'] . " uk {$g['panjang']} x {$g['lebar']} x {$g['tebal']}";

                    $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', abs($diffBanyak), abs($diffM3), $hargaPatok, $valueSelisih, 'm');
                    $totalDebit        += $valueSelisih;
                }
            }

            // ============================================================
            // 4. KREDIT: Gaji Pegawai Repair
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $totalGaji           = $jmlPekerja * 150000;
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, 0, 150000, $totalGaji, 'b'); // ✅ fix: masuk kredit
                $totalKredit        += $totalGaji;
            }

            // ============================================================
            // 5. DEBIT/PENYEIMBANG: HPP Repair
            // ============================================================
            $selisih     = $totalDebit - $totalKredit;
            $jurnalBlock = array_merge($jurnalBlockDebit, $jurnalBlockKredit); // ✅ fix: debit dulu, kredit kemudian

            if (round($selisih, 2) != 0) {
                $jurnalBlock[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'k', 0, 0, abs($selisih), abs($selisih), 'm'); // ✅ fix: hpp selalu paling bawah
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
