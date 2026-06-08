<?php

namespace App\Exports\Sheets;

use App\Models\ProduksiHp;
use App\Models\BahanPenolongProduksi;
use App\Models\HargaPegawai;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class LaporanProduksiHotPressJurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithMapping
{
    protected string $tanggal;
    protected string $domain;
    protected int $rowIndex = 0;

    public function __construct(string $tanggal, string $domain)
    {
        $this->tanggal = $tanggal;
        $this->domain = $domain;
    }

    public function title(): string { return 'Jurnal'; }

    public function columnWidths(): array {
        return [
            'A' => 42, 'B' => 15, 'C' => 10, 'D' => 12, 'E' => 10, 
            'F' => 10, 'G' => 18, 'H' => 42, 'I' => 6,  'J' => 10, 
            'K' => 10, 'L' => 15, 'M' => 15, 'N' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    private function isWhn(): bool {
        return str_contains(strtolower($this->domain), 'wahana');
    }

    private function getNamaMesinSingkat(string $namaMesin, string $shift): string
    {
        $namaLower = strtolower($namaMesin);
        $shiftStr = strtolower(trim($shift)); 
        
        if (str_contains($namaLower, '1')) return "hp 1 {$shiftStr}";
        if (str_contains($namaLower, '2')) return "hp 2 {$shiftStr}";
        if (str_contains($namaLower, '3')) return "hp 3 {$shiftStr}";
        
        return "hp 1 {$shiftStr}"; 
    }

    // --- MAPPING AKUN HASIL ---
    private function getAkunProduk(string $tipe, float $tebal, string $jenisKayu, string $grade): array
    {
        $tipeStr = strtolower(trim($tipe));
        $gradeStr = strtolower(trim($grade));
        $kayuSingkat = str_contains(strtolower($jenisKayu), 'sengon') ? 's' : 'm';
        $tebalBulat = (int) $tebal;

        if ($tipeStr === 'platform') {
            $namaAkun = "platform {$tebalBulat} {$gradeStr} MTH";
        } else {
            $namaAkun = "{$tebalBulat}{$kayuSingkat} {$gradeStr} MTH";
        }

        $daftarAkun = [
            '12m better mth' => '1506.05', '15s aj mth' => '1506.14',
            '15s uty lokal mth' => '1506.13', '18s aj mth' => '1506.19',
            '3m uty lokal mth' => '1506.21', '5s uty lokal mth' => '1506.26',
            'platform 18 aj mth' => '1506.51',
        ];

        $key = strtolower($namaAkun);
        $noAkun = $daftarAkun[$key] ?? '1506.99';

        return ['nama' => $namaAkun, 'no' => $noAkun];
    }

    // --- MAPPING AKUN VENEER ---
    private function getAkunVeneer(float $tebal, string $jenisKayu): array
    {
        $kayu = str_contains(strtolower($jenisKayu), 'sengon') ? 'sengon' : 'meranti';
        $kelompok = ($tebal < 1) ? '260 face/back' : '130 core';
        $sf = $this->isWhn() ? 'WHN' : 'WJY';
        $ext = $this->isWhn() ? '01' : '00';

        $namaAkun = "Veneer Jadi {$kelompok} {$kayu} {$sf}";
        if ($kayu === 'meranti') {
            $noAkun = ($tebal < 1) ? "1442.{$ext}" : "1447.{$ext}";
        } else {
            $noAkun = ($tebal < 1) ? "1441.{$ext}" : "1446.{$ext}";
        }

        return ['nama' => $namaAkun, 'no' => $noAkun];
    }

    // --- MAPPING AKUN PENOLONG ---
    private function getAkunPenolong(string $namaBahan): array
    {
        $namaLower = strtolower(trim($namaBahan));
        $daftarPenolong = [
            'lem aruki' => '1507.2', 'lem hq' => '1507.57',
            'tepung wjy' => '1507.62', 'tepung' => '1507.20',
        ];
        
        $noAkun = '1507.99'; 
        foreach ($daftarPenolong as $keyword => $no) {
            if (str_contains($namaLower, $keyword)) {
                $noAkun = $no; break;
            }
        }
        return ['nama' => $namaBahan, 'no' => $noAkun];
    }

    // --- MAPPING AKUN HPP ---
    private function getAkunHpp(string $tipe): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        if (strtolower(trim($tipe)) === 'platform') return ['nama' => 'hpp platform', 'no' => "6111.{$ext}"];
        return ['nama' => 'hpp triplek', 'no' => "6111.{$ext}"];
    }

    // --- MAPPING AKUN GAJI ---
    private function getAkunGaji(): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        return [
            'biaya'  => ['nama' => 'Biaya Gaji Plywood', 'no' => "5114.{$ext}"],
            'hutang' => ['nama' => 'Hutang Gaji Plywood', 'no' => '2111.02'], 
        ];
    }

    // --- LOGIKA HARGA HPP PRODUK ---
    private function getHargaHpp(string $tipe, float $tebal, string $jenisKayu, string $grade): float
    {
        if (strtolower(trim($tipe)) === 'platform') return 2000000; 

        $kayu = strtolower($jenisKayu);
        $gr = strtolower($grade);
        $tbl = (string) round($tebal, 1); 

        if (str_contains($kayu, 'sengon')) {
            if (str_contains($gr, 'better')) {
                $p = ['4.7'=>65400, '7.7'=>96800, '9.1'=>100800, '12.1'=>97400, '12.4'=>103300, '15.1'=>132300, '15.5'=>153800, '18.5'=>173800];
                return $p[$tbl] ?? 96800;
            } elseif (str_contains($gr, 'fm')) {
                $p = ['9'=>156843, '12'=>207903, '15'=>258963, '18'=>291708];
                return $p[(string)round($tebal)] ?? 207903;
            } else {
                $p = ['4.7'=>47000, '7.7'=>66400, '9.1'=>73000, '12.1'=>107100, '12.4'=>106400, '15.1'=>122900, '15.5'=>125400, '18.5'=>135900];
                return $p[$tbl] ?? 73000;
            }
        } else {
            if (str_contains($gr, 'better')) {
                $p = ['5.1'=>70300, '8.2'=>78300, '8.7'=>94300];
                return $p[$tbl] ?? 78300;
            } elseif (str_contains($gr, 'lokal')) {
                $p = ['5.1'=>70300, '8.2'=>90800, '11.9'=>96300, '14.8'=>124300, '17.8'=>153300];
                return $p[$tbl] ?? 90800;
            } else {
                $p = ['2.8'=>40000, '5.1'=>65500, '8.2'=>79500, '8.7'=>76500, '11.9'=>103500, '14.8'=>112000, '17.8'=>113500];
                return $p[$tbl] ?? 79500;
            }
        }
    }

    private function makeRow($namaAkun, $noAkun, $tgl, $namaProduksi, $ket, $map, $hitKbk, $banyak, $m3, $harga): array
    {
        return [ $namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $ket, $map, $hitKbk, $banyak, $m3, $harga, null ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        $produksis = ProduksiHp::with([
            'triplekHasilHp.mesin', 'triplekHasilHp.barangSetengahJadi.jenisBarang', 'triplekHasilHp.barangSetengahJadi.ukuran', 'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.mesin', 'platformHasilHp.barangSetengahJadi.jenisBarang', 'platformHasilHp.barangSetengahJadi.ukuran', 'platformHasilHp.barangSetengahJadi.grade',
            'bahanHotpress.barangSetengahJadi.jenisBarang', 'bahanHotpress.barangSetengahJadi.ukuran',
            'bahanPenolongHp', 'detailPegawaiHp'
        ])->whereDate('tanggal_produksi', $this->tanggal)->get();

        if ($produksis->isEmpty()) return $rows;
        
        $tglStr = Carbon::parse($this->tanggal)->format('d-m-Y');
        $hargaPegawaiMaster = HargaPegawai::first()->harga ?? 115000;

        foreach ($produksis as $prod) {
            $shiftStr = $prod->shift ?? 'pagi';
            $hasilByMesin = [];

            foreach ($prod->triplekHasilHp as $t) {
                $hasilByMesin[$t->id_mesin][] = ['tipe' => 'triplek', 'data' => $t];
            }
            foreach ($prod->platformHasilHp as $p) {
                $hasilByMesin[$p->id_mesin][] = ['tipe' => 'platform', 'data' => $p];
            }

            $hp3Id = null;
            foreach ($hasilByMesin as $mId => $items) {
                $namaMesin = strtolower($items[0]['data']->mesin->nama_mesin ?? '');
                if (str_contains($namaMesin, '3')) { $hp3Id = $mId; break; }
            }
            if (!$hp3Id && count($hasilByMesin) > 0) { $hp3Id = array_key_last($hasilByMesin); }

            $jumlahPekerja = $prod->detailPegawaiHp->count();

            foreach ($hasilByMesin as $mId => $items) {
                $namaMesinAsli = $items[0]['data']->mesin->nama_mesin ?? "HOTPRESS 1";
                $namaMesinSingkat = $this->getNamaMesinSingkat($namaMesinAsli, $shiftStr);
                
                $totalHargaProdukHp3 = 0;
                $totalHargaBahanGlobal = 0; 

                // --- 1. JURNAL HASIL PRODUKSI ---
                foreach ($items as $item) {
                    $tipe = $item['tipe'];
                    $data = $item['data'];
                    
                    $u = $data->barangSetengahJadi->ukuran ?? null;
                    $jk = $data->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                    $gr = $data->barangSetengahJadi->grade->nama_grade ?? 'uty lokal';
                    $tebal = $u->tebal ?? 0;
                    $banyak = $data->isi ?? 0;
                    $m3 = ($u->panjang * $u->lebar * $tebal * $banyak) / 1000000000;

                    $akunProduk = $this->getAkunProduk($tipe, $tebal, $jk, $gr);
                    $hargaHpp = $this->getHargaHpp($tipe, $tebal, $jk, $gr);
                    
                    $m3Round = round($m3, 4);
                    $totalProdValue = $banyak * $hargaHpp;

                    // DEBIT - Hasil Produksi (hit kbk 'b')
                    $rows[] = $this->makeRow($akunProduk['nama'], $akunProduk['no'], $tglStr, $namaMesinSingkat, $akunProduk['nama'], 'd', 'b', $banyak, $m3Round, $hargaHpp);
                    
                    // KREDIT - HPP Mesin (Selain HP 3)
                    if ($mId != $hp3Id) {
                        $akunHpp = $this->getAkunHpp($tipe);
                        $rows[] = $this->makeRow($akunHpp['nama'], $akunHpp['no'], $tglStr, $namaMesinSingkat, '', 'k', '', '', '', $totalProdValue);
                    } else {
                        $totalHargaProdukHp3 += $totalProdValue;
                    }
                }

                // --- 2. DUMP GLOBAL BIAYA & GAJI DI MESIN HP 3 ---
                if ($mId == $hp3Id) {
                    
                    // Pemakaian Bahan Baku Veneer Kering (Kredit)
                    foreach ($prod->bahanHotpress as $bahan) {
                        $u = $bahan->barangSetengahJadi->ukuran ?? null;
                        $jkAsli = $bahan->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                        $tebal = $u->tebal ?? 0;
                        $banyak = $bahan->isi ?? 0;
                        $m3 = ($u->panjang * $u->lebar * $tebal * $banyak) / 1000000000;

                        $akunVeneer = $this->getAkunVeneer($tebal, $jkAsli);
                        $hargaVeneer = ($tebal < 1) ? 2800000 : 1900000; 
                        
                        $m3Round = round($m3, 4);
                        $totalHargaBahanGlobal += ($m3Round * $hargaVeneer);

                        $tipeVeneer = ($tebal < 1) ? '260 F/B' : '130 Core';
                        $ketVeneer = "{$tipeVeneer} {$jkAsli} uk {$tebal}";

                        $rows[] = $this->makeRow($akunVeneer['nama'], $akunVeneer['no'], $tglStr, $namaMesinSingkat, $ketVeneer, 'k', 'm', $banyak, $m3Round, $hargaVeneer);
                    }

                    // Pemakaian Bahan Penolong (Kredit)
                    foreach ($prod->bahanPenolongHp as $penolong) {
                        $akunPenolong = $this->getAkunPenolong($penolong->nama_bahan);
                        $banyak = $penolong->jumlah;
                        
                        $masterPenolong = BahanPenolongProduksi::where('nama_bahan_penolong', $penolong->nama_bahan)->first();
                        $hargaPenolong = $masterPenolong ? $masterPenolong->harga : 50000;

                        $totalHargaBahanGlobal += ($banyak * $hargaPenolong);

                        $rows[] = $this->makeRow($akunPenolong['nama'], $akunPenolong['no'], $tglStr, $namaMesinSingkat, '', 'k', 'b', $banyak, '', $hargaPenolong);
                    }

                    // Jurnal Gaji Pekerja (Di HP 3)
                    if ($jumlahPekerja > 0) {
                        $akunGaji = $this->getAkunGaji();
                        $rows[] = $this->makeRow($akunGaji['biaya']['nama'], $akunGaji['biaya']['no'], $tglStr, $namaMesinSingkat, '', 'd', 'b', $jumlahPekerja, '', $hargaPegawaiMaster);
                        $rows[] = $this->makeRow($akunGaji['hutang']['nama'], $akunGaji['hutang']['no'], $tglStr, $namaMesinSingkat, '', 'k', 'b', $jumlahPekerja, '', $hargaPegawaiMaster);
                    }

                    // SATU BARIS HPP GLOBAL DI HP 3 (Debit, Penyeimbang Akhir)
                    $nilaiHppHp3 = $totalHargaBahanGlobal - $totalHargaProdukHp3;
                    
                    if ($nilaiHppHp3 != 0) {
                        $akunHppGlobal = $this->getAkunHpp('triplek');
                        $rows[] = $this->makeRow($akunHppGlobal['nama'], $akunHppGlobal['no'], $tglStr, $namaMesinSingkat, '', 'd', '', '', '', $nilaiHppHp3);
                    }
                }
            }

            // Jeda 1 baris kosong HANYA SETELAH SATU SHIFT SELESAI
            $rows[] = array_fill(0, 14, ''); 
        }

        return $rows;
    }

    public function map($row): array
    {
        $this->rowIndex++; 
        if ($this->rowIndex === 1 || implode('', (array)$row) === '') {
            return $row;
        }

        $r = $this->rowIndex;
        $row[13] = "=IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r}))";
        
        return $row;
    }
}