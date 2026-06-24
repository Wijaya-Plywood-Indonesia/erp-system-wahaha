<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Schemas;

use App\Models\ReferensiHargaProduksi;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ReferensiHargaProduksiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama')
                    ->maxLength(255)
                    ->placeholder('Masukkan nama referensi (opsional)'),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->relationship('ukuran', 'panjang')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->panjang}mm x {$record->lebar}mm x {$record->tebal}mm")
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Ukuran'),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_kayu} - {$record->nama_kayu}")
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Jenis Kayu'),

                Select::make('id_sub_anak_akun')
                    ->label('Sub Anak Akun')
                    ->relationship('subAnakAkun', 'nama_sub_anak_akun')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_sub_anak_akun} - {$record->nama_sub_anak_akun}")
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Sub Anak Akun'),

                TextInput::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->options(function ($state) {
                        $defaults = [
                            'Afalan' => 'Afalan',
                            'Veneer Basah' => 'Veneer Basah',
                            'Veneer Kering' => 'Veneer Kering',
                            'Veneer Jadi' => 'Veneer Jadi',
                            'Platform' => 'Platform',
                            'Lain-Lain' => 'Lain-Lain',
                        ];
                        $dbValues = \App\Models\ReferensiHargaProduksi::whereNotNull('jenis_barang')
                            ->where('jenis_barang', '!=', '')
                            ->distinct()
                            ->pluck('jenis_barang', 'jenis_barang')
                            ->toArray();

                        $options = array_merge($defaults, $dbValues);

                        if ($state && !array_key_exists($state, $options)) {
                            $options[$state] = $state;
                        }

                        return $options;
                    })
                    ->searchable()
                    ->native(false)
                    ->placeholder('Pilih atau buat baru')
                    ->createOptionForm([
                        TextInput::make('jenis_barang')
                            ->label('Jenis Barang Baru')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Contoh: Veneer Basah'),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        return $data['jenis_barang'];
                    }),

                TextInput::make('kw')
                    ->label('KW')
                    ->options(function ($state) {
                        $options = \App\Models\ReferensiHargaProduksi::whereNotNull('kw')
                            ->where('kw', '!=', '')
                            ->distinct()
                            ->pluck('kw', 'kw')
                            ->toArray();

                        if ($state && !array_key_exists($state, $options)) {
                            $options[$state] = $state;
                        }

                        return $options;
                    })
                    ->searchable()
                    ->native(false)
                    ->placeholder('Pilih atau buat baru')
                    ->createOptionForm([
                        TextInput::make('kw')
                            ->label('KW Baru')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Contoh: KW 1'),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        return $data['kw'];
                    }),

                TextInput::make('harga')
                    ->label('Harga Produksi')
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : null)
                    ->dehydrateStateUsing(fn($state) => blank($state) ? null : str_replace('.', '', $state))
                    ->placeholder('0'),
            ]);
    }
}
