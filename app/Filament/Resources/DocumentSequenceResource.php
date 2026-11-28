<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\DocumentSequenceResource\Pages;
use App\Models\DocumentSequence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
            Forms\Components\Toggle::make('yearly_reset')->label('ترقيم سنوي')->live()
                ->helperText('يبدأ من 1 كل سنة ويظهر العام في الرقم (مثل INV-2026-000001)'),
            Forms\Components\TextInput::make('next_number')->label('الرقم التالي')
                ->numeric()->integer()->required()
                ->minValue(fn ($record, Get $get) => $get('yearly_reset') ? 1 : (int) $record?->next_number)
                ->helperText('في الترقيم العادي يمكن زيادته فقط؛ وإن كان الرقم مستخدماً يتخطاه النظام تلقائياً'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('المستند')->weight('bold'),
                Tables\Columns\TextColumn::make('prefix')->label('البادئة'),
                Tables\Columns\TextColumn::make('padding')->label('الخانات'),
                Tables\Columns\IconColumn::make('yearly_reset')->label('سنوي')->boolean(),
                Tables\Columns\TextColumn::make('next_number')->label('الرقم التالي'),
                Tables\Columns\TextColumn::make('example')->label('الرقم القادم سيكون')
                    ->state(fn ($record) => $record->format($record->next_number)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->mutateFormDataUsing(function (array $data) {
                    // عند التفعيل يُحتسب العدّاد على السنة الحالية فيُحترم الرقم المُدخل
                    $data['period'] = ! empty($data['yearly_reset']) ? (int) now()->format('Y') : null;

                    return $data;
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocumentSequences::route('/')];
    }
}
