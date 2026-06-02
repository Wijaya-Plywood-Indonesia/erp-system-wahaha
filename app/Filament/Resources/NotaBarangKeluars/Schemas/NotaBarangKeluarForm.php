<?php

namespace App\Filament\Resources\NotaBarangKeluars\Schemas;

use App\Services\NomorNotaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class NotaBarangKeluarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->default(today())
                    ->required()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        self::refreshNomorNota($get, $set);
                    })
                    ->live(),

                Select::make('tipe_nota')
                    ->label('Tipe Nota')
                    ->options([
                        'BK'  => 'BK – Barang Keluar (Pabrik)',
                        'BKL' => 'BKL – Barang Keluar (Lain-lain)',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        self::refreshNomorNota($get, $set);
                    }),

                // Tampilan saja — tidak dikirim ke DB
                TextInput::make('no_nota_display')
                    ->label('No. Nota')
                    ->placeholder('(pilih tipe nota dulu)')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->no_nota);
                        }
                    }),

                // Yang dikirim ke DB
                Hidden::make('no_nota'),

                TextInput::make('tujuan_nota')
                    ->label('Kepada')
                    ->required(),

                Hidden::make('dibuat_oleh')
                    ->default(fn() => auth()->id()),

                TextInput::make('dibuat_oleh_display')
                    ->label('Dibuat Oleh')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->pembuat?->name ?? '-');
                        } else {
                            $component->state(auth()->user()->name);
                        }
                    }),
            ]);
    }

    /**
     * Generate nomor nota dan set ke field display + hidden.
     * Dipanggil setiap kali tipe_nota atau tanggal berubah.
     */
    private static function refreshNomorNota(Get $get, Set $set): void
    {
        $tipe    = $get('tipe_nota');
        $tanggal = $get('tanggal');

        // Hanya generate kalau keduanya sudah terisi
        if (! $tipe || ! $tanggal) {
            $set('no_nota_display', null);
            $set('no_nota', null);
            return;
        }

        $nomor = NomorNotaService::generate($tipe, Carbon::parse($tanggal));

        $set('no_nota_display', $nomor); // tampilan
        $set('no_nota', $nomor);         // hidden — yang dikirim ke DB
    }
}
