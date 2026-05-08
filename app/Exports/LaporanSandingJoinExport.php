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
use App\Filament\Pages\LaporanSandingJoin\Queries\LoadLaporanSandingJoin;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanSandingJoinExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData,
        protected string $tanggal
    ) {}

    public function sheets(): array
    {
        $rawCollection = LoadLaporanSandingJoin::run($this->tanggal);

        return [
            new LaporanSandingJoinDetailSheet($this->detailData),
            new LaporanSandingJoinSummarySheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (dari kode existing kamu)
// ============================================================
class LaporanSandingJoinDetailSheet implements FromCollection, WithHeadings, WithTitle
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
            $first       = $items->first();
            $pekerja     = $first['pekerja'] ?? [];
            $target      = (int) $first['target'];
            $hasil       = (int) $first['hasil'];
            $selisih     = (int) $first['selisih'];
            $jenisBarang = $first['jenis_barang'] ?? $first['jenis_kayu'] ?? '-';

            $rows->push(['MEJA / AREA SANDING', $first['nomor_meja']]);
            $rows->push(['UKURAN',               $first['ukuran']]);
            $rows->push(['JENIS KAYU/BARANG',    $jenisBarang]);
            $rows->push(['GRADE / KW',            $first['kw']]);
            $rows->push(['TANGGAL PRODUKSI',      $first['tanggal']]);
            $rows->push([]);

            $rows->push([
                'ID PEGAWAI',
                'Nama Lengkap',
                'Jam Masuk',
                'Jam Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan',
                '',
                'Target Harian',
                'Hasil Produksi',
                'Selisih',
            ]);

            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);
                $rows->push([
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $p['jam_masuk']  ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin']       ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    $target,
                    $hasil,
                    $selisih >= 0 ? '+' . $selisih : $selisih,
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                count($pekerja) . ' Orang',
                '',
                '',
                '',
                $totalPotongan > 0 ? $totalPotongan : '-',
                '',
                '',
                $target,
                $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
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
        return 'Laporan Sanding Joint';
    }
}

// ============================================================
// SHEET 2: SUMMARY
//
// Kolom: A=Tanggal | B=p | C=l | D=t | E=jenis | F=kw2 | G=kw3 | H=byk | I=m3 | J=TTL PKJ
//
// Struktur:
//   [Grand Total row] — kuning, di Row 2
//   Per baris hasil:
//   [Tgl] [p] [l] [t] [jenis] [kw2] [kw3] [byk] [m3] [ttl_pkj]
// ============================================================
class LaporanSandingJoinSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $grandTotalRow = [];

    public function __construct(protected $rawCollection) {}

    public function collection()
    {
        $rows    = collect();
        $allRows = [];

        foreach ($this->rawCollection as $produksi) {
            $tanggal       = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $jumlahPekerja = (int) $produksi->pegawaiSandingJoint->count();

            // ✅ Group langsung dari hasilSandingJoint per id_ukuran + kw
            $hasilGroups = $produksi->hasilSandingJoint
                ->groupBy(fn($h) => $h->id_ukuran . '|' . $h->kw);

            foreach ($hasilGroups as $hasilItems) {
                $firstHasil  = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran;
                $jenisKayu   = $firstHasil->jenisKayu;

                // Dimensi ukuran
                $p = $ukuranModel->panjang ?? '';
                $l = $ukuranModel->lebar   ?? '';
                $t = $ukuranModel->tebal   ?? '';

                // Jenis kayu
                $jenis = strtolower($jenisKayu->kode_kayu ?? $jenisKayu->nama_kayu ?? '-');

                // kw2 = kw input (grade masuk), kw3 = kw output (grade keluar)
                // Sesuaikan field ini dengan model HasilSandingJoint kamu
                $kw2 = $firstHasil->kw      ?? '-';  // grade masuk
                $kw3 = $firstHasil->kw_out  ?? '-';  // grade keluar — sesuaikan field

                // byk = total jumlah lembar
                $byk = (int) $hasilItems->sum('jumlah');

                // m3 = volume (panjang x lebar x tebal x jumlah / 1.000.000)
                // Sesuaikan satuan jika p/l dalam mm dan t dalam mm
                $m3 = 0;
                if ($p && $l && $t && $byk) {
                    $m3 = round(($p * $l * $t * $byk) / 1000000000, 3); // mm³ → m³
                }

                $allRows[] = [
                    'tanggal'   => $tanggal,
                    'p'         => $p,
                    'l'         => $l,
                    't'         => $t,
                    'jenis'     => $jenis,
                    'kw2'       => $kw2,
                    'kw3'       => $kw3,
                    'byk'       => $byk,
                    'm3'        => $m3,
                    'ttl_pkj'   => $jumlahPekerja,
                ];
            }
        }

        // Grand Total
        $grandByk = collect($allRows)->sum('byk');
        $grandM3  = round(collect($allRows)->sum('m3'), 3);
        $grandPkj = collect($allRows)->sum('ttl_pkj');

        // Row 2: Grand Total (kuning)
        $rows->push([
            '',           // A - Tanggal
            '',           // B - p
            '',           // C - l
            '',           // D - t
            '',           // E - jenis
            '',           // F - kw2
            '',           // G - kw3
            $grandByk,    // H - byk
            $grandM3,     // I - m3
            $grandPkj,    // J - TTL PKJ
        ]);

        // Data rows mulai Row 3
        foreach ($allRows as $row) {
            $rows->push([
                $row['tanggal'],  // A
                $row['p'],        // B
                $row['l'],        // C
                $row['t'],        // D
                $row['jenis'],    // E
                $row['kw2'],      // F
                $row['kw3'],      // G
                $row['byk'],      // H
                $row['m3'],       // I
                $row['ttl_pkj'],  // J
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'p', 'l', 't', 'jenis', 'kw2', 'kw3', 'byk', 'm3', 'TTL PKJ'];
    }

    public function title(): string
    {
        return 'Summary Sanding Joint';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Row 1: Header biru
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2: Grand Total kuning terang
                $sheet->getStyle('A2:J2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 3+: Data
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:J{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Kolom A (Tanggal) → rata kiri
                $sheet->getStyle("A3:A{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Auto-size semua kolom
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
