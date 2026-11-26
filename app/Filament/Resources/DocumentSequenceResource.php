<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\DocumentSequenceResource\Pages;
use App\Models\DocumentSequence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentSequenceResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'sequences.manage';

    protected static ?string $model = DocumentSequence::class;
    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?string $modelLabel = 'ترقيم';
    protected static ?string $pluralModelLabel = 'ترقيم المستندات';
    protected static ?string $navigationLabel = 'ترقيم المستندات';
    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('prefix')->label('البادئة (مثل INV-)')
                ->required()->maxLength(10),
            Forms\Components\TextInput::make('padding')->label('عدد خانات الرقم')
                ->numeric()->integer()->minValue(4)->maxValue(10)->required(),
            Forms\Components\TextInput::make('next_number')->label('الرقم التالي')
                ->numeric()->integer()->required()
                ->minValue(fn ($record) => (int) $record?->next_number)
                ->helperText('يمكن زيادته فقط، حتى لا يتكرر رقم صدر سابقاً'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('المستند')->weight('bold'),
                Tables\Columns\TextColumn::make('prefix')->label('البادئة'),
                Tables\Columns\TextColumn::make('padding')->label('الخانات'),
                Tables\Columns\TextColumn::make('next_number')->label('الرقم التالي'),
                Tables\Columns\TextColumn::make('example')->label('الرقم القادم سيكون')
                    ->state(fn ($record) => $record->format($record->next_number)),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocumentSequences::route('/')];
    }
}
