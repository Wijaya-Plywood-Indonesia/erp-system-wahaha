<?php

namespace App\Exports;

use App\Models\ProduksiTembeltriplek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanTembelTriplekExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected string $tanggal;
    protected array $laporanRingkasan;

    // ✅ Disimpan untuk dipakai di styles() — menandai baris mana yang perlu diformat
    protected int $barisAwalDetail = 0;
    protected int $barisTotalRingkasan = 0;

    public function __construct(string $tanggal, array $laporanRingkasan)
    {
        $this->tanggal = $tanggal;
        $this->laporanRingkasan = $laporanRingkasan;

        Log::info('[LaporanTembelTriplekExport] Export dimulai', [
            'tanggal' => $tanggal,
            'jumlah_pegawai' => count($laporanRingkasan),
        ]);
    }

    public function array(): array
    {
        $rows = [];

        // ===== BAGIAN 1: RINGKASAN PER PEGAWAI =====
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

        // ✅ Baris total ringkasan
        $totalModal = array_sum(array_column($this->laporanRingkasan, 'modal'));
        $totalHasil = array_sum(array_column($this->laporanRingkasan, 'total'));
        $totalSelisih = array_sum(array_column($this->laporanRingkasan, 'selisih'));
        $rows[] = ['', 'TOTAL', '', '', '', $totalModal, $totalHasil, $totalSelisih, ''];

        // Posisi baris total ringkasan (heading 3 baris + jumlah data + 1)
        $this->barisTotalRingkasan = 3 + count($this->laporanRingkasan) + 1;

        // Baris kosong pemisah
        $rows[] = ['', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', '', ''];

        // ===== BAGIAN 2: DETAIL PER HASIL/BARANG =====
        $rows[] = ['DETAIL HASIL PER BARANG', '', '', '', '', '', '', '', ''];
        $rows[] = ['Barang', 'Modal (Pcs)', 'Hasil (Pcs)', 'Selisih (Pcs)', 'Pegawai Terlibat', 'Jumlah Orang', 'No. Palet', '', ''];

        $this->barisAwalDetail = count($rows) + 3; // +3 karena 3 baris heading di awal sheet

        $detailRows = $this->ambilDetailHasil();
        foreach ($detailRows as $d) {
            $rows[] = $d;
        }

        Log::info('[LaporanTembelTriplekExport] Total baris disusun', [
            'total_baris' => count($rows),
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

                $rows[] = [
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
        $tanggalFormat = Carbon::parse($this->tanggal)->format('d/m/Y');

        return [
            ["Laporan Tembel Triplek - {$tanggalFormat}"],
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
        return [
            'A' => 14,
            'B' => 25,
            'C' => 10,
            'D' => 10,
            'E' => 35,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 25,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // ✅ Judul utama
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ✅ Header tabel ringkasan
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');

        // ✅ Baris total ringkasan — bold
        $sheet->getStyle("A{$this->barisTotalRingkasan}:I{$this->barisTotalRingkasan}")
            ->getFont()->setBold(true);

        // ✅ Header section "DETAIL HASIL PER BARANG"
        $barisJudulDetail = $this->barisAwalDetail - 1;
        $sheet->mergeCells("A{$barisJudulDetail}:G{$barisJudulDetail}");
        $sheet->getStyle("A{$barisJudulDetail}")->getFont()->setBold(true)->setSize(12);

        // ✅ Header tabel detail
        $sheet->getStyle("A{$this->barisAwalDetail}:G{$this->barisAwalDetail}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$this->barisAwalDetail}:G{$this->barisAwalDetail}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        return [];
    }
}
