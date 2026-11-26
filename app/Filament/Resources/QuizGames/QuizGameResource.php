<?php

namespace App\Filament\Resources\QuizGames;

use App\Enums\AgeGroup;
use App\Enums\QuestionType;
use App\Filament\Resources\QuizGames\Pages\CreateQuizGame;
use App\Filament\Resources\QuizGames\Pages\EditQuizGame;
use App\Filament\Resources\QuizGames\Pages\ListQuizGames;
use App\Models\QuizGame;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kuiz Interaktif (user decision 2026-11-26): made by ASUH (admins and
 * editors), published by a Super Admin after checking, then played by
 * children, parents and pupils whose teacher assigns it.
 */
class QuizGameResource extends Resource
{
    protected static ?string $model = QuizGame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Kuiz interaktif';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'kuiz interaktif';

    protected static ?string $pluralModelLabel = 'Kuiz interaktif';

    protected static ?string $slug = 'kuiz-interaktif';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Maklumat kuiz')->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label('Tajuk')->required()->maxLength(150)->columnSpanFull(),
                Textarea::make('description')->label('Penerangan ringkas (pilihan)')->rows(2)->maxLength(500)->columnSpanFull(),
                Select::make('age_group')->label('Umur')->options(AgeGroup::class)->required()->native(false),
                Select::make('category_id')->label('Kategori (pilihan)')->relationship('category', 'name')->native(false),
                TextInput::make('seconds_per_question')->label('Masa setiap soalan')->integer()->minValue(5)->maxValue(300)->default(20)->suffix('saat')->required(),
                FileUpload::make('cover_path')->label('Gambar muka (pilihan, 16:9)')->image()->disk('public')->directory('quiz-games')->visibility('public')->maxSize(5120)
                    ->imageResizeMode('contain')->imageResizeTargetWidth('1280')->imageResizeTargetHeight('720')->imageResizeUpscale(false),
            ]),

            Section::make('Soalan')->columnSpanFull()
                ->description('Seret untuk susun. Masa dan mata dikira sendiri: jawapan betul dapat 500 mata, ditambah sehingga 500 lagi jika cepat, dan bonus untuk jawapan betul berturut-turut.')
                ->schema([
                    Repeater::make('questions')->relationship()->hiddenLabel()->orderColumn('sort')->reorderable()
                        ->collapsible()->cloneable()
                        ->itemLabel(fn (array $state) => filled($state['prompt'] ?? null) ? str($state['prompt'])->limit(70) : 'Soalan baharu')
                        ->addActionLabel('Tambah soalan')
                        ->minItems(1)
                        ->columns(2)
                        ->schema([
                            Select::make('type')->label('Jenis soalan')->options(QuestionType::class)->default(QuestionType::Choice->value)->required()->native(false)->live(),
                            TextInput::make('seconds')->label('Masa khas (pilihan)')->integer()->minValue(5)->maxValue(300)->suffix('saat')
                                ->helperText('Kosongkan untuk guna masa kuiz.'),
                            Textarea::make('prompt')->label('Soalan')->required()->rows(2)->maxLength(500)->columnSpanFull(),
                            FileUpload::make('image_path')->label('Gambar (pilihan)')->image()->disk('public')->directory('quiz-games/questions')->visibility('public')->maxSize(5120)
                                ->imageResizeMode('contain')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('900')->imageResizeUpscale(false)->columnSpanFull(),

                            // Choices (with the right one ticked), or the items in their right order.
                            Repeater::make('options')->label(fn (Get $get) => self::type($get) === QuestionType::Order ? 'Item dalam tertib yang BETUL (pemain akan dapat susunan rawak)' : 'Pilihan jawapan')
                                ->visible(fn (Get $get) => in_array(self::type($get), [QuestionType::Choice, QuestionType::Order], true))
                                ->schema([
                                    TextInput::make('t')->label('Teks')->required()->maxLength(150),
                                    Toggle::make('ok')->label('Jawapan betul')->inline(false)
                                        ->visible(fn (Get $get) => self::type($get, '../../') === QuestionType::Choice),
                                ])
                                ->columns(2)->columnSpanFull()
                                ->minItems(fn (Get $get) => self::type($get) === QuestionType::Order ? 3 : 2)
                                ->maxItems(fn (Get $get) => self::type($get) === QuestionType::Order ? 8 : 4)
                                ->defaultItems(fn (Get $get) => self::type($get) === QuestionType::Order ? 3 : 4)
                                ->addActionLabel('Tambah')
                                ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                    if (self::type($get) === QuestionType::Choice && collect($value)->where('ok', true)->count() !== 1) {
                                        $fail('Tandakan tepat satu jawapan betul.');
                                    }
                                }),

                            Select::make('tf_answer')->label('Jawapan')->options(['1' => 'Betul', '0' => 'Salah'])->native(false)
                                ->visible(fn (Get $get) => self::type($get) === QuestionType::TrueFalse)
                                ->required(fn (Get $get) => self::type($get) === QuestionType::TrueFalse),
                            TagsInput::make('fill_answers')->label('Jawapan yang diterima')->placeholder('Taip jawapan, tekan Enter')
                                ->helperText('Boleh beberapa ejaan. Huruf besar/kecil dan tanda baca tidak dikira.')
                                ->visible(fn (Get $get) => self::type($get) === QuestionType::Fill)
                                ->required(fn (Get $get) => self::type($get) === QuestionType::Fill)
                                ->columnSpanFull(),

                            Textarea::make('explanation')->label('Penerangan selepas menjawab (pilihan)')->rows(2)->maxLength(500)->columnSpanFull(),
                        ])
                        ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => self::fromAnswer($data))
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::toAnswer($data))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::toAnswer($data)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Tajuk')->searchable(),
                TextColumn::make('age_group')->label('Umur'),
                TextColumn::make('questions_count')->label('Soalan')->counts('questions'),
                TextColumn::make('plays_count')->label('Dimainkan')->counts('plays'),
                TextColumn::make('published_at')->label('Status')->badge()
                    ->state(fn (QuizGame $record) => $record->isPublished() ? 'Diterbitkan' : 'Draf')
                    ->color(fn (string $state) => $state === 'Diterbitkan' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('age_group')->label('Umur')->options(AgeGroup::class),
                TernaryFilter::make('published_at')->label('Status')->nullable()->trueLabel('Diterbitkan')->falseLabel('Draf'),
            ])
            ->recordActions([
                self::publishAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label(fn (QuizGame $record) => $record->isPublished() ? 'Nyahterbit' : 'Terbitkan')
            ->icon(fn (QuizGame $record) => $record->isPublished() ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedCheckCircle)
            ->color(fn (QuizGame $record) => $record->isPublished() ? 'gray' : 'success')
            // Content checked before children see it: Super Admin only.
            ->visible(fn () => auth()->user()?->isAdmin() ?? false)
            ->requiresConfirmation()
            ->modalDescription(fn (QuizGame $record) => $record->isPublished()
                ? 'Kuiz ini akan hilang daripada anak, ibu bapa dan guru. Markah lama disimpan.'
                : 'Pastikan semua soalan dan jawapan sudah disemak (termasuk syariah). Kuiz dipaparkan bila suis "Buka Kuiz Interaktif" di Tetapan hidup.')
            ->action(function (QuizGame $record) {
                if (! $record->isPublished() && $record->questions()->doesntExist()) {
                    Notification::make()->warning()->title('Tambah soalan dahulu')->send();

                    return;
                }
                $record->update(['published_at' => $record->isPublished() ? null : now()]);
                Notification::make()->success()->title($record->isPublished() ? 'Kuiz diterbitkan' : 'Kuiz disembunyikan')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizGames::route('/'),
            'create' => CreateQuizGame::route('/create'),
            'edit' => EditQuizGame::route('/{record}/edit'),
        ];
    }

    private static function type(Get $get, string $path = ''): ?QuestionType
    {
        return self::asType($get($path.'type'));
    }

    private static function asType(mixed $type): ?QuestionType
    {
        return $type instanceof QuestionType ? $type : QuestionType::tryFrom((string) $type);
    }

    /** Stored answer to the form's fields. */
    private static function fromAnswer(array $data): array
    {
        $type = self::asType($data['type'] ?? null);
        $data['tf_answer'] = $type === QuestionType::TrueFalse ? ((($data['answer'] ?? [])[0] ?? false) ? '1' : '0') : null;
        $data['fill_answers'] = $type === QuestionType::Fill ? ($data['answer'] ?? []) : [];

        return $data;
    }

    /** The form's fields to what is stored for each kind of question. */
    private static function toAnswer(array $data): array
    {
        $type = self::asType($data['type'] ?? null);
        $data['answer'] = match ($type) {
            QuestionType::TrueFalse => [(string) ($data['tf_answer'] ?? '0') === '1'],
            QuestionType::Fill => array_values(array_filter(array_map('trim', $data['fill_answers'] ?? []))),
            default => null,
        };
        $data['options'] = match ($type) {
            QuestionType::Choice => array_values(array_map(fn ($o) => ['t' => trim($o['t']), 'ok' => (bool) ($o['ok'] ?? false)], $data['options'] ?? [])),
            QuestionType::Order => array_values(array_map(fn ($o) => ['t' => trim($o['t'])], $data['options'] ?? [])),
            default => null,
        };
        unset($data['tf_answer'], $data['fill_answers']);

        return $data;
    }
}
