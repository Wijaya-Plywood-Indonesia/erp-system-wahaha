<?php

namespace App\Filament\Pages;

use App\Exports\LaporanTembelTriplekExport;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\ProduksiTembeltriplek;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class LaporanTembelTriplek extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Tembel Triplek';
    protected string $view = 'filament.pages.laporan-tembel-triplek';
    protected static ?int $navigationSort = 10;

    // ✅ Satu sumber kebenaran — properti Livewire langsung
    public string $tanggal = '';
    public array $laporan = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');

        Log::info('[LaporanTembelTriplek] mount() dipanggil', [
            'tanggal_awal' => $this->tanggal,
            'user_id'      => auth()->id(),
        ]);

        $this->form->fill([
            'tanggal' => $this->tanggal,
        ]);

        $this->loadData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->format('Y-m-d')
                ->maxDate(now())
                ->default(now())
                ->live()
                ->closeOnDateSelection()
                ->columnSpanFull()
                ->afterStateUpdated(function (?string $state) {
                    Log::info('[LaporanTembelTriplek] afterStateUpdated dipanggil', [
                        'state_diterima' => $state,
                        'tipe'           => gettype($state),
                    ]);

                    // ✅ Guard: kalau state null atau kosong, jangan lanjut
                    if (blank($state)) {
                        Log::warning('[LaporanTembelTriplek] state kosong/null, loadData dibatalkan');
                        return;
                    }

                    // ✅ Validasi format sebelum assign
                    try {
                        $parsed = Carbon::createFromFormat('Y-m-d', $state);
                        $this->tanggal = $parsed->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::error('[LaporanTembelTriplek] Format tanggal tidak valid', [
                            'state'   => $state,
                            'message' => $e->getMessage(),
                        ]);
                        return;
                    }

                    Log::info('[LaporanTembelTriplek] Tanggal diperbarui', [
                        'tanggal_baru' => $this->tanggal,
                    ]);

                    $this->loadData();
                })
                ->suffixIcon('heroicon-o-calendar')
                ->suffixIconColor('primary'),
        ];
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

    public function loadData(): void
    {
        Log::info('[LaporanTembelTriplek] loadData() dipanggil', [
            'tanggal'   => $this->tanggal,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // ✅ Guard awal: tanggal wajib ada
        if (blank($this->tanggal)) {
            Log::warning('[LaporanTembelTriplek] loadData() dibatalkan — tanggal kosong');
            return;
        }

        try {
            $this->isLoading = true;
            $this->laporan   = [];

            $raw = ProduksiTembeltriplek::with([
                'pegawaiTembeltriplek.pegawai.hasilTembeltriplek.barangSetengahJadi.jenisBarang',
                'pegawaiTembeltriplek.pegawai.hasilTembeltriplek.barangSetengahJadi.ukuran',
                'pegawaiTembeltriplek.pegawai.hasilTembeltriplek.barangSetengahJadi.grade',
                'pegawaiTembeltriplek.pegawai.hasilTembeltriplek.pegawais',
            ])
                ->whereDate('tanggal', $this->tanggal)
                ->get();

            Log::info('[LaporanTembelTriplek] Query selesai', [
                'tanggal'        => $this->tanggal,
                'jumlah_produksi' => $raw->count(),
            ]);

            if ($raw->isEmpty()) {
                Log::info('[LaporanTembelTriplek] Tidak ada record produksi untuk tanggal ini');

                Notification::make()
                    ->warning()
                    ->title('Data Tidak Ditemukan')
                    ->body("Tidak ada sesi produksi tembel triplek untuk tanggal {$this->tanggal}.")
                    ->send();

                return;
            }

            $this->laporan = $this->transformData($raw);

            Log::info('[LaporanTembelTriplek] transformData selesai', [
                'jumlah_baris' => count($this->laporan),
            ]);

            if (empty($this->laporan)) {
                Log::warning('[LaporanTembelTriplek] Produksi ada tapi laporan kosong setelah transform');

                Notification::make()
                    ->warning()
                    ->title('Data Pegawai Kosong')
                    ->body("Sesi produksi tanggal {$this->tanggal} ditemukan, tapi belum ada pegawai yang terdaftar.")
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title('Data Berhasil Dimuat')
                ->body(count($this->laporan) . ' pegawai ditemukan untuk tanggal ' . $this->tanggal . '.')
                ->send();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('[LaporanTembelTriplek] QueryException', [
                'tanggal' => $this->tanggal,
                'sql'     => $e->getSql(),
                'bindings' => $e->getBindings(),
                'message' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Error Database')
                ->body('Query gagal. Silakan cek log untuk detail.')
                ->persistent()
                ->send();
        } catch (\Error $e) {
            Log::error('[LaporanTembelTriplek] PHP Error', [
                'tanggal' => $this->tanggal,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Error Sistem')
                ->body('Terjadi error sistem. Silakan cek log untuk detail.')
                ->persistent()
                ->send();
        } catch (\Exception $e) {
            Log::error('[LaporanTembelTriplek] Exception', [
                'tanggal' => $this->tanggal,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Terjadi Kesalahan')
                ->body('Silakan cek log untuk detail.')
                ->persistent()
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
            $kendala       = $produksi->kendala ?: '-';

            // ✅ Tahap 1: Hitung distribusi presisi untuk SETIAP hasil produksi ini
            // Key: id pegawai, Value: akumulasi modal/hasil/nama barang
            $distribusiPerPegawai = [];

            foreach ($produksi->hasilTembeltriplek as $hasil) {
                $pegawaiTerlibat = $hasil->pegawais; // Collection of Pegawai

                if ($pegawaiTerlibat->isEmpty()) {
                    Log::warning('[LaporanTembelTriplek] Hasil tanpa pegawai terlibat', [
                        'hasil_id' => $hasil->id,
                    ]);
                    continue;
                }

                $jumlahOrang = $pegawaiTerlibat->count();

                // ✅ Distribusi presisi modal
                $modalTerbagi = $this->bagiPresisi($hasil->modal, $jumlahOrang);
                $hasilTerbagi = $this->bagiPresisi($hasil->hasil, $jumlahOrang);

                $namaBarang = optional($hasil->barangSetengahJadi)->nama_lengkap ?? 'Tanpa Nama';
                $labelBarang = $jumlahOrang > 1
                    ? "{$namaBarang} (bersama {$jumlahOrang} orang)"
                    : $namaBarang;

                // ✅ Bagikan ke masing-masing pegawai sesuai urutan distribusi
                foreach ($pegawaiTerlibat as $index => $pegawaiTerkait) {
                    $id = $pegawaiTerkait->id;

                    if (!isset($distribusiPerPegawai[$id])) {
                        $distribusiPerPegawai[$id] = [
                            'modal' => 0,
                            'hasil' => 0,
                            'barang' => [],
                        ];
                    }

                    $distribusiPerPegawai[$id]['modal']  += $modalTerbagi[$index];
                    $distribusiPerPegawai[$id]['hasil']  += $hasilTerbagi[$index];
                    $distribusiPerPegawai[$id]['barang'][] = $labelBarang;
                }
            }

            // ✅ Tahap 2: Susun baris laporan berdasarkan pegawai yang HADIR
            foreach ($produksi->pegawaiTembeltriplek as $pegawaiRecord) {
                $pegawai = $pegawaiRecord->pegawai;

                if (is_null($pegawai)) {
                    continue;
                }

                $data = $distribusiPerPegawai[$pegawai->id] ?? ['modal' => 0, 'hasil' => 0, 'barang' => []];

                $result[] = [
                    'kodep'      => $pegawai->kodep ?? $pegawai->kode_pegawai ?? '-',
                    'nama'       => $pegawai->nama ?? $pegawai->nama_pegawai ?? '-',
                    'jam_masuk'  => $pegawaiRecord->jam_masuk ? Carbon::parse($pegawaiRecord->jam_masuk)->format('H:i') : '-',
                    'jam_pulang' => $pegawaiRecord->jam_pulang ? Carbon::parse($pegawaiRecord->jam_pulang)->format('H:i') : '-',
                    'hasil'      => !empty($data['barang']) ? implode(', ', array_unique($data['barang'])) : '(belum ada hasil)',
                    'modal'      => $data['modal'],
                    'total'      => $data['hasil'],
                    'selisih'    => $data['hasil'] - $data['modal'],
                    'kendala'    => $pegawaiRecord->keterangan,
                ];
            }
        }

        return $result;
    }

    /**
     * ✅ Largest Remainder Method
     * Membagi $total ke $jumlahOrang bagian secara presisi (integer),
     * memastikan jumlah seluruh bagian SELALU sama dengan $total asli.
     *
     * @return array<int> array berisi pembagian untuk setiap orang, urutan sesuai index asal
     */
    private function bagiPresisi(int $total, int $jumlahOrang): array
    {
        if ($jumlahOrang <= 0) {
            return [$total];
        }

        $dasar = intdiv($total, $jumlahOrang);
        $sisa  = $total % $jumlahOrang;
        $bagian = array_fill(0, $jumlahOrang, $dasar);
        for ($i = 0; $i < $sisa; $i++) {
            $bagian[$i] += 1;
        }

        return $bagian;
    }

    public function refresh(): void
    {
        Log::info('[LaporanTembelTriplek] refresh() dipanggil', [
            'tanggal' => $this->tanggal,
        ]);

        $this->loadData();

        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->send();
    }

    public function exportExcel()
    {
        Log::info('[LaporanTembelTriplek] exportExcel() dipanggil', [
            'tanggal'      => $this->tanggal,
            'jumlah_baris' => count($this->laporan),
        ]);

        if (empty($this->laporan)) {
            Notification::make()
                ->warning()
                ->title('Tidak Ada Data')
                ->body('Tidak ada data untuk diexport pada tanggal ini.')
                ->send();

            return;
        }

        try {
            $namaFile = 'laporan-tembel-triplek-' . $this->tanggal . '.xlsx';

            return Excel::download(
                new LaporanTembelTriplekExport($this->tanggal, $this->laporan),
                $namaFile
            );
        } catch (\Exception $e) {
            Log::error('[LaporanTembelTriplek] Export gagal', [
                'tanggal' => $this->tanggal,
                'message' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Export Gagal')
                ->body('Terjadi kesalahan. Silakan cek log.')
                ->persistent()
                ->send();
        }
    }
}
