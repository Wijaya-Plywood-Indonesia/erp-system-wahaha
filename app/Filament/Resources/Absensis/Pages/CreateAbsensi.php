<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use App\Services\AbsensiParsingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');
        $nextDate = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) {
            return;
        }

        /** @var AbsensiParsingService $parsingService */
        $parsingService = app(AbsensiParsingService::class);
        $parseResult = $parsingService->collectLogs($files, $targetDate, $nextDate);

        $rawLogs = $parseResult['raw_logs'];
        $skippedLines = $parseResult['skipped_lines'];
        $totalProcessed = 0;

        // ================================================
        // STEP 2: PROSES MERGE — Kelompokkan Berdasarkan Kedekatan Shift Master
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Urutkan semua log gabungan (Kantor A + Kantor B) secara kronologis dari pagi ke malam
            $sorted = collect($entries)->sortBy('full');

            // Ambil acuan aturan shift resmi milik pegawai dari database master
            $pegawai = Pegawai::where('kode_pegawai', $empCode)->first();

            // Set default jadwal kerja normal/pagi jika master data kosong
            $jadwalMasuk  = $pegawai->jam_masuk_sistem ?? '07:00:00';
            $jadwalPulang = $pegawai->jam_pulang_sistem ?? '16:00:00';

            // Deteksi apakah sistem mengonfigurasi pegawai ini sebagai SHIFT MALAM
            $isShiftMalamSistem = Carbon::parse($jadwalMasuk)->hour >= 14;

            $jamMasukLog  = null;
            $jamPulangLog = null;

            $minSelisihMasuk  = 999999;
            $minSelisihPulang = 999999;

            foreach ($sorted as $entry) {
                $logTime = Carbon::parse($entry['full']);

                // Proyeksikan waktu kerja ideal di database pada tanggal target
                $targetMasukDt  = Carbon::parse("$targetDate $jadwalMasuk");
                $targetPulangDt = $isShiftMalamSistem
                    ? Carbon::parse("$targetDate $jadwalPulang")->addDay() // Shift malam pulang di esok pagi hari
                    : Carbon::parse("$targetDate $jadwalPulang");

                // Hitung kedekatan (selisih menit absolut) waktu tap log terhadap waktu ideal shift
                $selisihKeMasuk  = abs($logTime->diffInMinutes($targetMasukDt));
                $selisihKePulang = abs($logTime->diffInMinutes($targetPulangDt));

                // PILIH STRATEGI TERDEKAT (Mencegah Tertukar/Terbalik)
                if ($selisihKeMasuk < $selisihKePulang) {
                    // Log ini dinilai paling masuk akal sebagai JAM MASUK
                    // Toleransi maksimal keterlambatan/kecepatan tap adalah 8 jam (480 menit)
                    if ($selisihKeMasuk < $minSelisihMasuk && $selisihKeMasuk < 480) {
                        $minSelisihMasuk = $selisihKeMasuk;
                        $jamMasukLog     = $entry['time'];
                    }
                } else {
                    // Log ini dinilai paling masuk akal sebagai JAM PULANG
                    if ($selisihKePulang < $minSelisihPulang && $selisihKePulang < 480) {
                        $minSelisihPulang = $selisihKePulang;
                        $jamPulangLog      = $entry['time'];
                    }
                }
            }

            // ================================================
            // STEP 3: SIMPAN HASIL SINKRONISASI KE DATABASE
            // ================================================
            if ($jamMasukLog || $jamPulangLog) {
                // updateOrCreate memastikan jika data tanggal tersebut sudah ada akan diperbarui, jika belum ada akan dibuat baru
                DetailAbsensi::updateOrCreate(
                    ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                    [
                        'id_absensi' => $record->id,
                        'jam_masuk'  => $jamMasukLog,
                        'jam_pulang' => $jamPulangLog,
                    ]
                );
                $totalProcessed++;
            }
        }

        // Kirimkan notifikasi keberhasilan di Filament v4
        Notification::make()
            ->success()
            ->title('Import Berhasil')
            ->body("Berhasil memproses & menyatukan $totalProcessed data absensi pegawai.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
