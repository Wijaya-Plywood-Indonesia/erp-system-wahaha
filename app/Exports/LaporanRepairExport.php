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
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (tidak berubah)
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
            $rows->push(['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target / Jam', 'Hasil', 'Selisih']);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',
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
// SHEET 2: SUMMARY — Baca langsung dari Eloquent Collection
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    // Terima raw Eloquent collection, BUKAN array hasil transformer
    public function __construct(protected $rawCollection) {}

    public function collection()
    {
        $summary = [];

        // =======================================================
        // STEP 1: Loop langsung ke model, ambil dimensi dari relasi
        // Tidak ada risiko field hilang karena kita baca langsung!
        // =======================================================
        foreach ($this->rawCollection as $produksi) {

            $tanggal = Carbon::parse($produksi->tanggal)->format('d/m/Y');

            foreach ($produksi->modalRepairs as $modal) {

                $ukuran    = $modal->ukuran;    // → relasi langsung ke model Ukuran
                $jenisKayu = $modal->jenisKayu; // → relasi langsung ke model JenisKayu

                // ✅ Ambil dimensi langsung dari model, pasti akurat
                $p     = (float) ($ukuran->panjang ?? 0);
                $l     = (float) ($ukuran->lebar   ?? 0);
                $t     = (float) ($ukuran->tebal   ?? 0);
                $jenis = $jenisKayu->nama_kayu ?? '-';
                $kw    = $modal->kw ?? $modal->kualitas ?? 1;

                // Key grouping unik per kombinasi dimensi
                $key = "{$p}|{$l}|{$t}|{$jenis}|{$kw}";

                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,   // ✅ Panjang → Kolom B
                        'l'           => $l,   // ✅ Lebar   → Kolom C
                        't'           => $t,   // ✅ Tebal   → Kolom D (float! bukan string)
                        'jenis'       => $jenis,
                        'kw'          => $kw,
                        'byk'         => 0,
                        'pekerja_ids' => [],
                    ];
                }

                // Hitung hasil & kumpulkan ID pekerja
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (! $rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu <= 0) continue;

                    $summary[$key]['byk']           += $hasilIndividu;
                    $summary[$key]['pekerja_ids'][]  = $rp->pegawai->id;
                }
            }
        }

        // =======================================================
        // STEP 2: Build rows dengan Formula Excel untuk M3
        //
        // Layout:
        // A=Tanggal | B=P | C=L | D=T | E=Jenis | F=KW | G=Byk | H=M3 | I=TTL PKJ
        //
        // Row 1 = Heading (dari headings())
        // Row 2 = TOTAL
        // Row 3+ = Data
        // =======================================================

        $rows        = collect();
        $dataStart   = 3;
        $lastDataRow = $dataStart + count($summary) - 1;

        // Baris TOTAL (Row 2) - SUM formula agar auto update
        $rows->push([
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            "=SUM(G{$dataStart}:G{$lastDataRow})", // Total Byk
            "=SUM(H{$dataStart}:H{$lastDataRow})", // Total M3
            "=SUM(I{$dataStart}:I{$lastDataRow})", // Total Pekerja
        ]);

        // Baris data mulai row 3
        $rowNum = $dataStart;
        foreach ($summary as $s) {
            $uniquePekerja = count(array_unique($s['pekerja_ids']));

            $rows->push([
                $s['tanggal'],  // A
                $s['p'],        // B - Panjang (angka)
                $s['l'],        // C - Lebar   (angka)
                $s['t'],        // D - Tebal   (angka float, misal 0.5)
                $s['jenis'],    // E - Jenis Kayu
                $s['kw'],       // F - KW

                // G - Byk: jumlah lembar hasil produksi
                $s['byk'],

                // H - M3: Formula Excel langsung!
                // Rumus: (P * L * T) / 1.000.000.000 * Byk
                "=B{$rowNum}*C{$rowNum}*D{$rowNum}/1000000000*G{$rowNum}",

                // I - Total Pekerja unik
                $uniquePekerja,
            ]);

            $rowNum++;
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'P (mm)', 'L (mm)', 'T (mm)', 'Jenis', 'KW', 'Byk (Lbr)', 'M3', 'TTL PKJ'];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Row 1: Header biru
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2: Total kuning
                $sheet->getStyle('A2:I2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 3+: Data
                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:I{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Format kolom H (M3) & H2 (total M3) → 4 desimal
                    $sheet->getStyle("H2:H{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.0000');
                }

                // Auto-size semua kolom
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
