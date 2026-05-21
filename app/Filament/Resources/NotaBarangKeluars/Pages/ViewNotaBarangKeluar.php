<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use App\Services\VeneerMutasiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewNotaBarangKeluar extends ViewRecord
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validasi')
                ->label('Validasi Nota')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Validasi Nota BK')
                ->modalDescription('Stok veneer akan langsung dikurangi setelah Anda memvalidasi nota ini. Tindakan ini tidak dapat dibatalkan.')
                ->modalSubmitActionLabel('Ya, Validasi Sekarang')
                ->visible(fn () => $this->record->divalidasi_oleh === null)
                ->action(function () {
                    try {
                        // Guard: cannot validate own nota
                        if ((int) $this->record->dibuat_oleh === (int) auth()->id()) {
                            Notification::make()
                                ->title('Validasi Gagal')
                                ->body('Anda tidak dapat memvalidasi nota yang Anda buat sendiri. Minta akun lain untuk memvalidasi.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $hasMutasi = \App\Models\VeneerMutasi::where('id_nota_bk', $this->record->id)->exists();
                        app(VeneerMutasiService::class)->processStockFromNota($this->record);

                        Notification::make()
                            ->title('Nota berhasil divalidasi')
                            ->body($hasMutasi ? 'Stok veneer telah dikurangi sesuai isi nota BK.' : 'Status nota telah diperbarui.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['divalidasi_oleh']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Validasi Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn () => $this->record->divalidasi_oleh === null),
        ];
    }
}
