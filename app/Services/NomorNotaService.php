<?php

namespace App\Services;

use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use Illuminate\Support\Carbon;

class NomorNotaService
{
    public static function generateBarangKeluar(string $tipe, Carbon $tanggal): string
    {
        return self::generate($tipe, $tanggal, NotaBarangKeluar::class);
    }

    public static function generateBarangMasuk(string $tipe, Carbon $tanggal): string
    {
        return self::generate($tipe, $tanggal, NotaBarangMasuk::class);
    }

    public static function generate(string $tipe, Carbon $tanggal, string $model): string
    {
        $bulan  = $tanggal->format('m');
        $hari   = $tanggal->format('d');
        $suffix = $bulan . $hari;

        $existing = $model::query()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('no_nota', 'like', "{$tipe}-%")
            ->pluck('no_nota');

        $usedSequences = $existing->map(function ($nota) {
            $parts = explode('-', $nota);
            return isset($parts[1]) ? (int) $parts[1] : 0;
        })->filter()->sort()->values();

        $lastSequence = $usedSequences->last() ?? 0;
        $nextSequence = ($lastSequence >= 10000) ? 1 : $lastSequence + 1;

        return "{$tipe}-" . str_pad($nextSequence, 5, '0', STR_PAD_LEFT) . "-{$suffix}";
    }
}
