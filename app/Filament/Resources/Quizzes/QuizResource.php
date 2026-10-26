<?php

namespace App\Filament\Resources\Quizzes;

use App\Filament\Resources\Quizzes\Pages\CreateQuiz;
use App\Filament\Resources\Quizzes\Pages\EditQuiz;
use App\Filament\Resources\Quizzes\Pages\ListQuizzes;
use App\Models\Quiz;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * One quiz per book: 3–5 multiple-choice questions, passed at 80%
 * (CLAUDE.md). Editors manage quizzes alongside books.
 */
class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Kuiz';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'kuiz';

    protected static ?string $pluralModelLabel = 'Kuiz';

    protected static ?string $slug = 'kuiz';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** Letters shown for the answer choices. */
    private const LETTERS = ['A', 'B', 'C', 'D'];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Kuiz')
                    ->columns(2)
                    ->schema([
                        Select::make('book_id')->label('Buku')
                            ->relationship('book', 'title')
                            ->searchable()->preload()
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Buku ini sudah mempunyai kuiz.'])
                            ->required(),
                        Toggle::make('is_active')->label('Aktif (dipaparkan kepada anak)')->default(true)->inline(false),
                    ]),
                Section::make('Soalan')
                    ->description('Tulis '.Quiz::MIN_QUESTIONS.'–'.Quiz::MAX_QUESTIONS.' soalan. Anak lulus jika '.Quiz::PASS_PERCENT.'% betul. Jawapan tidak didedahkan kepada anak, jadi mereka perlu membaca semula sebelum mencuba lagi.')
                    ->schema([
                        Repeater::make('questions')->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->minItems(Quiz::MIN_QUESTIONS)
                            ->maxItems(Quiz::MAX_QUESTIONS)
                            ->defaultItems(Quiz::MIN_QUESTIONS)
                            ->addActionLabel('Tambah soalan')
                            ->itemLabel(fn (array $state) => $state['question'] ?? null)
                            ->schema([
                                TextInput::make('question')->label('Soalan')->required()->maxLength(300),
                                Repeater::make('options')->label('Pilihan jawapan')
                                    ->simple(TextInput::make('text')->required()->maxLength(120))
                                    ->minItems(3)->maxItems(4)->defaultItems(3)
                                    ->hint('Pilihan pertama ialah A, kedua B, dan seterusnya.')
                                    ->addActionLabel('Tambah pilihan')
                                    ->reorderable(false)
                                    ->live(),
                                Select::make('correct_index')->label('Jawapan betul')
                                    // Answer choices are lettered in the order written above.
                                    ->options(fn (Get $get) => array_slice(
                                        array_map(fn (string $letter) => "Pilihan {$letter}", self::LETTERS),
                                        0, max(count($get('options') ?? []), 3)))
                                    ->rules([fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if ((int) $value >= count($get('options') ?? [])) {
                                            $fail('Pilih jawapan betul daripada pilihan yang ada.');
                                        }
                                    }])
                                    ->required()->native(false),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('book.title')->label('Buku')->searchable(),
                TextColumn::make('questions_count')->label('Soalan')->counts('questions')->alignEnd(),
                TextColumn::make('passed_count')->label('Anak lulus')
                    ->counts(['attempts as passed_count' => fn ($query) => $query->where('passed', true)])->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Dikemas kini')->date('j M Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizzes::route('/'),
            'create' => CreateQuiz::route('/cipta'),
            'edit' => EditQuiz::route('/{record}/ubah'),
        ];
    }
}
