<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\ProduksiTembeltriplek;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanTembelTriplek extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $title                        = 'Laporan Tembel Triplek';
    protected string $view                                 = 'filament.pages.laporan-tembel-triplek';
    protected static ?int $navigationSort                  = 10;

    // ✅ Property flat — langsung bisa di-bind wire:model di Blade
    public string $tanggal  = '';
    public array  $laporan  = [];
    public bool   $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->loadData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->refresh()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->laporan)),
        ];
    }

    // ✅ Livewire otomatis panggil ini saat $tanggal berubah dari Blade
    public function updatedTanggal(string $value): void
    {
        try {
            $this->tanggal = Carbon::parse($value)->format('Y-m-d');
            $this->loadData();
        } catch (Exception $e) {
            $this->tanggal = now()->format('Y-m-d');
        }
    }

    public function loadData(): void
    {
        try {
            $this->isLoading = true;
            $this->laporan   = [];

            $raw = ProduksiTembeltriplek::with([
                'pegawaiTembeltriplek.pegawai',
                'hasilTembeltriplek.barangSetengahJadi',
                'hasilTembeltriplek.pegawaiTembeltriplek.pegawai',
            ])
                ->whereDate('tanggal', $this->tanggal)
                ->get();

            if ($raw->isNotEmpty()) {
                $this->laporan = $this->transformData($raw);
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Data tembel triplek tidak ditemukan untuk tanggal tersebut.')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading tembel triplek data', ['message' => $e->getMessage()]);
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Terjadi kesalahan saat memuat data laporan.')
                ->send();
        } finally {
            $this->isLoading = false;
        }
    }

    private function transformData($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal)->format('d/m/Y');
            $jumlahPekerja = $produksi->pegawaiTembeltriplek->count();

            $hasilGroups = $produksi->hasilTembeltriplek
                ->groupBy('id_barang_setengah_jadi_hp');

            foreach ($hasilGroups as $hasilItems) {
                $firstHasil = $hasilItems->first();
                $barang     = $firstHasil->barangSetengahJadi;

                $totalModal = (int) $hasilItems->sum('modal');
                $totalHasil = (int) $hasilItems->sum('hasil');
                $nomorPalet = $hasilItems->pluck('nomor_palet')->filter()->unique()->implode(', ') ?: '-';

                $result[] = [
                    'tanggal'     => $tanggalFormat,
                    'nama_barang' => $barang->nama_barang ?? $barang->kode_barang ?? 'Tanpa Nama',
                    'nomor_palet' => $nomorPalet,
                    'total_modal' => $totalModal,
                    'total_hasil' => $totalHasil,
                    'selisih'     => $totalHasil - $totalModal,
                    'ttl_pkj'     => $jumlahPekerja,
                    'kendala'     => $produksi->kendala ?: '-',
                ];
            }
        }

        return $result;
    }

    public function refresh(): void
    {
        $this->loadData();
        Notification::make()->success()->title('Data Diperbarui')->send();
    }

    public function exportExcel(): void
    {
        Notification::make()->info()->title('Fitur Export sedang disiapkan')->send();
    }
}
