<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\DetailPegawai;
use App\Models\PegawaiSanding;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record     = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');
        $nextDate   = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) return;

        // Kumpulkan semua tap per pegawai
        // Struktur: $rawLogs['9199'] = [ ['date'=>..., 'time'=>..., 'full'=>...], ... ]
        $rawLogs       = [];
        $totalProcessed = 0;

        // ================================================
        // STEP 1: PARSING — Kumpulkan semua tap
        // ================================================
        foreach ($files as $file) {
            if (!Storage::disk('public')->exists($file)) continue;

            $fileContent = Storage::disk('public')->get($file);
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) continue;

                $parts = preg_split('/\s+/', $trimmedLine);

                // Cari posisi kolom tanggal (YYYY/MM/DD atau YYYY-MM-DD)
                $dateIndex = null;
                foreach ($parts as $i => $value) {
                    if (preg_match('/^\d{4}[\/\-]\d{2}[\/\-]\d{2}$/', $value)) {
                        $dateIndex = $i;
                        break;
                    }
                }
                if ($dateIndex === null) continue;

                try {
                    $dateStr   = Carbon::parse(str_replace('/', '-', $parts[$dateIndex]))->format('Y-m-d');
                    $timeStr   = $parts[$dateIndex + 1] ?? null;
                    $empCode   = ltrim($parts[2] ?? '', '0'); // EnNo selalu di index 2

                    if (!$empCode || !$timeStr) continue;

                    // Hanya ambil data tanggal target dan nextDate saja
                    if (!in_array($dateStr, [$targetDate, $nextDate])) continue;

                    $rawLogs[$empCode][] = [
                        'date' => $dateStr,
                        'time' => $timeStr,
                        'full' => Carbon::parse("$dateStr $timeStr"),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // ================================================
        // STEP 2: PROSES — Tentukan masuk & pulang per orang
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Urutkan semua tap dari yang paling awal
            $sorted = collect($entries)->sortBy('full');

            // Tap pertama di hari target = jam masuk
            $firstTap = $sorted->filter(fn($l) => $l['date'] === $targetDate)->first();
            if (!$firstTap) continue; // Tidak ada tap di hari target, skip

            $jamMasuk   = $firstTap['time'];
            $jamMasukDt = Carbon::parse($firstTap['full']);

            // ⭐ DETEKSI SHIFT DARI JAM MASUK
            // Shift Malam: tap masuk antara jam 14:00 - 23:59
            $isShiftMalam = $jamMasukDt->hour >= 14;

            if ($isShiftMalam) {
                // Shift Malam: cari tap pulang di nextDate (jam 00:00 - 10:00)
                $lastTap = $sorted
                    ->filter(fn($l) => $l['date'] === $nextDate
                        && Carbon::parse($l['full'])->hour <= 10)
                    ->last();
            } else {
                // Shift Pagi: cari tap pulang di hari yang sama, setelah jam masuk
                $lastTap = $sorted
                    ->filter(fn($l) => $l['date'] === $targetDate
                        && Carbon::parse($l['full'])->gt($jamMasukDt))
                    ->last();
            }

            $jamPulang = $lastTap['time'] ?? null;

            // ================================================
            // STEP 3: SIMPAN ke database
            // ================================================
            DetailAbsensi::updateOrCreate(
                ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                [
                    'id_absensi' => $record->id,
                    'jam_masuk'  => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                ]
            );

            $totalProcessed++;
        }

        Notification::make()
            ->success()
            ->title('Import Berhasil')
            ->body("Berhasil memproses $totalProcessed data pegawai.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
