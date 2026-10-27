<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Parents' testimonials for the /langganan sales page (user decision
 * 2026-10-27): a quote, a WhatsApp screenshot with names blurred, or both.
 * The section shows once at least two are published.
 */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Testimoni';

    protected static ?int $navigationSort = 16;

    protected static ?string $modelLabel = 'testimoni';

    protected static ?string $pluralModelLabel = 'Testimoni';

    protected static ?string $slug = 'testimoni';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('author')->label('Nama dipaparkan')->required()->maxLength(80)
                ->helperText('Contoh: Puan Aishah. Guna nama yang ibu bapa izinkan sahaja.'),
            TextInput::make('detail')->label('Keterangan (pilihan)')->maxLength(80)
                ->helperText('Contoh: Ibu 2 anak, Johor'),
            Textarea::make('quote')->label('Ayat testimoni (BM)')->rows(3)->maxLength(400)
                ->requiredWithout('image_path')->columnSpanFull(),
            Textarea::make('quote_en')->label('Ayat testimoni (English, pilihan)')->rows(3)->maxLength(400)->columnSpanFull(),
            FileUpload::make('image_path')->label('Tangkapan skrin WhatsApp (pilihan)')
                ->image()->disk('public')->directory('testimonials')->visibility('public')->maxSize(5120)
                ->imageResizeMode('contain')->imageResizeTargetWidth('1080')->imageResizeUpscale(false)
                ->imageEditor()
                ->helperText('Kaburkan nama dan nombor telefon sebelum muat naik (alat sunting gambar ada di sini). Dapatkan izin ibu bapa dahulu.')
                ->columnSpanFull(),
            TextInput::make('sort_order')->label('Susunan')->integer()->minValue(0)->default(0)
                ->helperText('Nombor kecil dipaparkan dahulu. Yang pertama yang ada ayat juga dipaparkan di bawah harga.'),
            Toggle::make('is_published')->label('Paparkan di laman')
                ->helperText(fn (Get $get) => 'Bahagian testimoni muncul di /langganan bila ada sekurang-kurangnya '.Testimonial::MIN_SHOWN.' testimoni dipaparkan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image_path')->label('Gambar')->disk('public')->imageHeight(48),
                TextColumn::make('author')->label('Nama')->description(fn (Testimonial $record) => $record->detail)->searchable(),
                TextColumn::make('quote')->label('Ayat')->limit(60)->placeholder('-'),
                ToggleColumn::make('is_published')->label('Dipaparkan'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTestimonials::route('/'),
        ];
    }
}
