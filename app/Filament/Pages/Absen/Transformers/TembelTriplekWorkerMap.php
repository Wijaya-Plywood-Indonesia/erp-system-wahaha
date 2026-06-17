<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;

class TembelTriplekWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan rincian barang setengah jadi yang ditambal
            $detailProduksi = [];
            if ($produksi->hasilTembeltriplek) {
                foreach ($produksi->hasilTembeltriplek as $detail) {
                    $b = $detail->barangSetengahJadi;

                    if ($b) {
                        $namaLengkapBarang = $b->nama_barang ?? $b->kode_barang ?? '-';
                    } else {
                        $namaLengkapBarang = 'Barang Tidak Diketahui';
                    }

                    $hasil = $detail->hasil ?? 0;
                    $modal = $detail->modal ?? 0;

                    // Format output: Nama Ukuran (Hasil: X Pcs / Modal: Y Pcs)
                    $detailProduksi[] = "{$namaLengkapBarang} (H: {$hasil} / M: {$modal})";
                }
            }

            $teksDetail = empty($detailProduksi) ? '-' : implode('; ', $detailProduksi);
            $labelHasil = "TEMBEL TRIPLEK: " . $teksDetail;

            // 2. Iterasi data Pegawai Tembel Triplek yang bekerja
            if ($produksi->pegawaiTembeltriplek) {
                foreach ($produksi->pegawaiTembeltriplek as $pg) {
                    if (!$pg->pegawai) continue;

                    $jamMasuk = $pg->jam_masuk ? Carbon::parse($pg->jam_masuk)->format('H:i:s') : '-';
                    $jamPulang = $pg->jam_pulang ? Carbon::parse($pg->jam_pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $pg->pegawai->kode_pegawai ?? '-',
                        'nama' => $pg->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pg->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $pg->keterangan ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
