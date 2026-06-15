<?php

namespace App\Filament\Resources\ProduksiTembelTripleks;

use App\Filament\Resources\ProduksiTembelTriplekResource\RelationManagers\PegawaiTembeltriplekRelationManager;
use App\Filament\Resources\ProduksiTembelTriplekResource\RelationManagers\HasilTembeltriplekRelationManager;
use App\Filament\Resources\ProduksiTembelTriplekResource\RelationManagers\BahanPenolongTembeltriplekRelationManager;
use App\Filament\Resources\ProduksiTembelTriplekResource\RelationManagers\ValidasiTembeltriplekRelationManager;
use App\Filament\Resources\ProduksiTembelTripleks\Pages\CreateProduksiTembelTriplek;
use App\Filament\Resources\ProduksiTembelTripleks\Pages\EditProduksiTembelTriplek;
use App\Filament\Resources\ProduksiTembelTripleks\Pages\ListProduksiTembelTripleks;
use App\Filament\Resources\ProduksiTembelTripleks\Schemas\ProduksiTembelTriplekForm;
use App\Filament\Resources\ProduksiTembelTripleks\Tables\ProduksiTembelTripleksTable;
use App\Models\ProduksiTembelTriplek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduksiTembelTriplekResource extends Resource
{
    protected static ?string $model = ProduksiTembelTriplek::class;
    protected static ?string $modelLabel = 'Produksi Tembel Triplek';
    protected static ?string $pluralModelLabel = 'Produksi Tembel Triplek';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = "Finishing";

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiTembelTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiTembelTripleksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiTembeltriplekRelationManager::class,
            HasilTembeltriplekRelationManager::class,
            BahanPenolongTembeltriplekRelationManager::class,
            ValidasiTembeltriplekRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiTembelTripleks::route('/'),
            'create' => CreateProduksiTembelTriplek::route('/create'),
            'edit' => EditProduksiTembelTriplek::route('/{record}/edit'),
        ];
    }
}
