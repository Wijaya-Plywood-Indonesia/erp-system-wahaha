<?php

namespace App\Filament\Resources\NotaBarangMasuks\Schemas;

use App\Services\NomorNotaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class NotaBarangMasukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->default(today())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::refreshNomorNota($get, $set);
                    }),

                Select::make('tipe_nota')
                    ->label('Tipe Nota')
                    ->options([
                        'BM'  => 'BM – Barang Masuk (Pabrik)',
                        'BML' => 'BML – Barang Masuk (Lain-lain)',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
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
                    ->default(fn() => auth()->id())
                    ->dehydrated(fn($context) => $context === 'create'),
                TextInput::make('dibuat_oleh_display')
                    ->label('Dibuat Oleh')
                    ->formatStateUsing(
                        fn($record) =>
                        $record?->dibuatOleh?->name ?? auth()->user()->name
                    )
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    private static function refreshNomorNota(Get $get, Set $set): void
    {
        $tipe    = $get('tipe_nota');
        $tanggal = $get('tanggal');

        if (! $tipe || ! $tanggal) {
            $set('no_nota_display', null);
            $set('no_nota', null);
            return;
        }

        $nomor = NomorNotaService::generateBarangMasuk($tipe, Carbon::parse($tanggal));

        $set('no_nota_display', $nomor);
        $set('no_nota', $nomor);
    }
}
