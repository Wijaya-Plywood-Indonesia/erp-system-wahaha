<?php

namespace App\Filament\Resources\ProduksiTembelTriplekResource\RelationManagers;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\PegawaiTembeltriplek;
use Illuminate\Database\Eloquent\Builder;

// Custom Schema & Table
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

// Table Columns & Custom Actions
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class HasilTembeltriplekRelationManager extends RelationManager
{
    // Sesuaikan dengan nama function relasi di model ProduksiTembeltriplek
    protected static string $relationship = 'hasilTembeltriplek';

    protected static ?string $title = 'Hasil Tembel Triplek';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('grade_id')
                    ->label('Filter Grade')
                    ->options(
                        Grade::whereHas('kategoriBarang', function ($q) {
                            $q->whereIn('nama_kategori', ['PLYWOOD', 'PLATFORM']);
                        })
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori') . ' | ' . $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi (Plywood)')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {
                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->whereIn('nama_kategori', ['PLYWOOD', 'PLATFORM']);
                            })
                            ->joinRelationship('jenisBarang')
                            ->joinRelationship('ukuran');

                        if ($get('grade_id')) {
                            $query->where('barang_setengah_jadi_hp.id_grade', $get('grade_id'));
                        }

                        if ($get('jenis_barang_id_filter')) {
                            $query->where('barang_setengah_jadi_hp.id_jenis_barang', $get('jenis_barang_id_filter'));
                        }

                        $query->orderBy('ukurans.tebal', 'asc')
                            ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                        return $query->get()->mapWithKeys(function ($b) {
                            return [
                                $b->id => ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                                    ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                                    ($b->grade?->nama_grade ?? '-') . ' | ' .
                                    ($b->jenisBarang?->nama_jenis_barang ?? '-')
                            ];
                        });
                    })
                    ->columnSpanFull(),

                TextInput::make('modal')
                    ->numeric()
                    ->required(),

                TextInput::make('hasil')
                    ->numeric()
                    ->required(),

                TextInput::make('nomor_palet')
                    ->numeric(),

                Select::make('id_pegawai_tembel_triplek')
                    ->label('Dikerjakan Oleh (Pegawai)')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function ($livewire) {
                        $produksiId = $livewire->ownerRecord?->id;
                        if (!$produksiId) return [];

                        return PegawaiTembeltriplek::with('pegawai')
                            ->where('id_produksi_tembel_triplek', $produksiId)
                            ->get()
                            ->mapWithKeys(fn($pt) => [
                                $pt->id => $pt->pegawai?->nama_pegawai ?? 'Unknown'
                            ]);
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) =>
                $query->with([
                    'pegawaiTembeltriplek.pegawai',
                    'barangSetengahJadi.ukuran',
                    'barangSetengahJadi.jenisBarang',
                    'barangSetengahJadi.grade.kategoriBarang',
                ])
            )
            ->groups([
                Group::make('id_pegawai_tembel_triplek')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        return 'Pegawai: ' . ($record->pegawaiTembeltriplek?->pegawai?->nama_pegawai ?? '-');
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id_pegawai_tembel_triplek')
            ->columns([
                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = $record->barangSetengahJadi;
                        if (!$b) return '-';

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                            ($b->grade?->nama_grade ?? '-') . ' | ' .
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->wrap(),

                TextColumn::make('modal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hasil')
                    ->numeric()
                    ->sortable()
                    ->color(fn($record) => $record->hasil < $record->modal ? 'danger' : 'success'),

                TextColumn::make('nomor_palet')
                    ->label('No. Palet')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}