<?php

namespace App\Support;

use App\Enums\AgeGroup;
use App\Enums\Game;

/**
 * What each Mod Anak game plays with, for the child's age group. Content is
 * kept to basics taught in Malaysian schools and must pass the syariah
 * panel before the admin switches the games on (Tetapan: games_open).
 */
class GameContent
{
    /** @return array<string, mixed> handed to the game's Alpine component as JSON */
    public static function for(Game $game, AgeGroup $age): array
    {
        $older = $age === AgeGroup::TenToThirteen;

        return match ($game) {
            Game::Memory => ['pairs' => array_slice(self::memoryPairs(), 0, $older ? 8 : 6)],
            Game::GoodChoice => ['rounds' => 8, 'scenarios' => array_merge(self::scenarios(), $older ? self::olderScenarios() : [])],
            Game::Order => ['sets' => array_merge(self::orderSets(), $older ? self::olderOrderSets() : [])],
            Game::Hijaiyah => ['rounds' => 10, 'questions' => self::hijaiyah($older)],
            Game::WordSearch => ['size' => $older ? 10 : 8, 'count' => $older ? 7 : 5, 'diagonal' => $older, 'words' => $older ? self::olderWords() : self::words()],
            Game::Prophets => ['rounds' => 8, 'questions' => self::prophets($older)],
            Game::QuickQuiz => ['rounds' => 10, 'seconds' => $older ? 10 : 15, 'questions' => self::manners($older)],
        };
    }

    /** @return list<array{0: string, 1: string}> emoji and its word */
    private static function memoryPairs(): array
    {
        return [
            ['🕌', 'Masjid'], ['📖', 'Al-Quran'], ['🕋', 'Kaabah'], ['📿', 'Tasbih'],
            ['🌙', 'Bulan sabit'], ['🌴', 'Pokok kurma'], ['🤲', 'Berdoa'], ['🐪', 'Unta'],
        ];
    }

    /** @return list<array{text: string, good: bool, why: string}> */
    private static function scenarios(): array
    {
        return [
            ['text' => 'Anis membantu ibu mengemas meja selepas makan.', 'good' => true, 'why' => 'Membantu ibu bapa ialah akhlak yang mulia.'],
            ['text' => 'Anas memberi salam ketika masuk ke rumah.', 'good' => true, 'why' => 'Memberi salam membawa kebaikan kepada seisi rumah.'],
            ['text' => 'Anis berkongsi bekal dengan kawan yang terlupa membawa makanan.', 'good' => true, 'why' => 'Berkongsi tanda kita sayang kepada kawan.'],
            ['text' => 'Anas membaca Bismillah sebelum makan.', 'good' => true, 'why' => 'Kita mulakan setiap perkara baik dengan nama Allah.'],
            ['text' => 'Anis memulangkan pensel yang dipinjam daripada kawan.', 'good' => true, 'why' => 'Memulangkan barang pinjaman ialah sifat amanah.'],
            ['text' => 'Anas meminta maaf selepas terlanggar kawan.', 'good' => true, 'why' => 'Meminta maaf menjaga hati kawan.'],
            ['text' => 'Anis berkata benar walaupun dia tersilap.', 'good' => true, 'why' => 'Jujur itu berani, dan Allah suka orang yang jujur.'],
            ['text' => 'Anas membuang sampah ke dalam tong sampah.', 'good' => true, 'why' => 'Menjaga kebersihan sebahagian daripada iman.'],
            ['text' => 'Anis berterima kasih apabila diberi hadiah.', 'good' => true, 'why' => 'Berterima kasih tanda kita menghargai orang lain.'],
            ['text' => 'Anas mengambil gula-gula kawan tanpa izin.', 'good' => false, 'why' => 'Kita mesti meminta izin dahulu sebelum mengambil barang orang.'],
            ['text' => 'Anis mengejek kawan yang terjatuh.', 'good' => false, 'why' => 'Mengejek menyakitkan hati. Bantulah kawan bangun.'],
            ['text' => 'Anas menjerit kepada adik kerana tidak mahu berkongsi mainan.', 'good' => false, 'why' => 'Bercakaplah dengan lembut dan berkongsilah dengan adik.'],
            ['text' => 'Anis berbohong kepada ibu tentang kerja sekolah.', 'good' => false, 'why' => 'Berbohong ialah bisikan penggoda. Berkata benarlah.'],
            ['text' => 'Anas terus bermain dan tidak menyahut apabila ayah memanggil.', 'good' => false, 'why' => 'Apabila ibu bapa memanggil, segeralah menyahut.'],
            ['text' => 'Anis membuang sampah ke dalam longkang.', 'good' => false, 'why' => 'Sampah dibuang ke dalam tong supaya alam kekal bersih.'],
            ['text' => 'Anas bercakap ketika guru sedang mengajar.', 'good' => false, 'why' => 'Kita menghormati guru dengan mendengar ketika guru mengajar.'],
        ];
    }

    /** @return list<array{text: string, good: bool, why: string}> */
    private static function olderScenarios(): array
    {
        return [
            ['text' => 'Anas meniru jawapan kawan semasa ujian.', 'good' => false, 'why' => 'Meniru ialah tidak jujur. Usaha sendiri lebih berkat.'],
            ['text' => 'Anis menyebarkan cerita buruk tentang kawan dalam kumpulan mesej kelas.', 'good' => false, 'why' => 'Mengumpat dan menyebar aib orang ialah dosa. Jagalah lidah dan jari.'],
            ['text' => 'Anas menegur kawannya dengan baik apabila kawan itu membuli orang lain.', 'good' => true, 'why' => 'Mencegah kemungkaran dengan cara yang baik ialah tanda keberanian.'],
            ['text' => 'Anis menyimpan baki duit belanja untuk bersedekah.', 'good' => true, 'why' => 'Sedekah tidak mengurangkan harta, malah membawa keberkatan.'],
            ['text' => 'Anas terus bermain permainan telefon sehingga lewat menunaikan solat.', 'good' => false, 'why' => 'Solat didahulukan, bermain kemudian.'],
            ['text' => 'Anis menolong jiran tua membawa barang dari kereta.', 'good' => true, 'why' => 'Berbuat baik kepada jiran dan orang tua ialah akhlak seorang Muslim.'],
        ];
    }

    /** @return list<array{title: string, items: list<string>}> each list in its correct order */
    private static function orderSets(): array
    {
        return [
            ['title' => 'Rukun Islam', 'items' => ['Mengucap dua kalimah syahadah', 'Menunaikan solat', 'Berpuasa di bulan Ramadan', 'Menunaikan zakat', 'Menunaikan haji bagi yang mampu']],
            ['title' => 'Solat fardu ikut waktu', 'items' => ['Subuh', 'Zohor', 'Asar', 'Maghrib', 'Isyak']],
            ['title' => 'Rukun Iman', 'items' => ['Beriman kepada Allah', 'Beriman kepada malaikat', 'Beriman kepada kitab-kitab', 'Beriman kepada rasul-rasul', 'Beriman kepada hari akhirat', 'Beriman kepada qada dan qadar']],
        ];
    }

    /** @return list<array{title: string, items: list<string>}> */
    private static function olderOrderSets(): array
    {
        return [
            ['title' => 'Fardu wuduk', 'items' => ['Niat', 'Membasuh muka', 'Membasuh kedua-dua tangan hingga siku', 'Menyapu sebahagian kepala', 'Membasuh kedua-dua kaki hingga buku lali', 'Tertib']],
            ['title' => 'Bulan dalam kalendar Hijrah', 'items' => ['Muharam', 'Safar', 'Rabiulawal', 'Rabiulakhir', 'Jamadilawal', 'Jamadilakhir', 'Rejab', 'Syaaban', 'Ramadan', 'Syawal', 'Zulkaedah', 'Zulhijah']],
        ];
    }

    /** @return list<array{prompt: string, big: string, answer: string, options: list<string>}> */
    private static function hijaiyah(bool $older): array
    {
        $letters = [
            ['ا', 'Alif'], ['ب', 'Ba'], ['ت', 'Ta'], ['ث', 'Tsa'], ['ج', 'Jim'], ['ح', 'Ha'], ['خ', 'Kho'],
            ['د', 'Dal'], ['ذ', 'Zal'], ['ر', 'Ra'], ['ز', 'Zai'], ['س', 'Sin'], ['ش', 'Syin'], ['ص', 'Sod'],
            ['ض', 'Dhod'], ['ط', 'Tho'], ['ظ', 'Zho'], ['ع', 'Ain'], ['غ', 'Ghain'], ['ف', 'Fa'], ['ق', 'Qof'],
            ['ك', 'Kaf'], ['ل', 'Lam'], ['م', 'Mim'], ['ن', 'Nun'], ['و', 'Wau'], ['ه', 'Ha'], ['ي', 'Ya'],
        ];
        $letters = $older ? $letters : array_slice($letters, 0, 14);
        // Two letters are both called "Ha": the game never offers a name twice.
        $names = array_values(array_unique(array_column($letters, 1)));

        return array_map(fn (array $letter) => [
            'prompt' => 'Apakah nama huruf ini?',
            'big' => $letter[0],
            'answer' => $letter[1],
            'options' => $names,
        ], $letters);
    }

    /** @return list<array{prompt: string, answer: string, options: list<string>}> */
    private static function prophets(bool $older): array
    {
        $clues = [
            ['Manusia pertama yang diciptakan Allah.', 'Nabi Adam'],
            ['Membina bahtera besar dengan perintah Allah.', 'Nabi Nuh'],
            ['Api tidak membakarnya dengan izin Allah.', 'Nabi Ibrahim'],
            ['Pernah ditelan oleh ikan besar.', 'Nabi Yunus'],
            ['Laut terbelah apabila baginda memukulnya dengan tongkat, dengan izin Allah.', 'Nabi Musa'],
            ['Memahami bahasa haiwan, termasuk semut.', 'Nabi Sulaiman'],
            ['Dicampak ke dalam perigi oleh abang-abangnya.', 'Nabi Yusuf'],
            ['Nabi dan rasul terakhir yang menerima Al-Quran.', 'Nabi Muhammad ﷺ'],
        ];

        if ($older) {
            $clues = array_merge($clues, [
                ['Menerima kitab Injil dan boleh bercakap ketika masih bayi.', 'Nabi Isa'],
                ['Menerima kitab Zabur dan mempunyai suara yang merdu.', 'Nabi Daud'],
                ['Sangat sabar ketika diuji dengan penyakit yang lama.', 'Nabi Ayub'],
                ['Mukjizatnya seekor unta betina yang keluar dari batu.', 'Nabi Saleh'],
                ['Air zamzam memancar berhampirannya ketika baginda masih kecil.', 'Nabi Ismail'],
                ['Berdoa memohon anak walaupun sudah tua, lalu dikurniakan Nabi Yahya.', 'Nabi Zakaria'],
            ]);
        }

        $names = array_column($clues, 1);

        return array_map(fn (array $clue) => ['prompt' => $clue[0], 'answer' => $clue[1], 'options' => $names], $clues);
    }

    /** @return list<array{prompt: string, answer: string, options: list<string>}> options include the answer */
    private static function manners(bool $older): array
    {
        $questions = [
            ['Apa kita ucap sebelum makan?', 'Bismillah', ['Alhamdulillah', 'Assalamualaikum', 'Subhanallah']],
            ['Apa kita ucap selepas makan?', 'Alhamdulillah', ['Bismillah', 'Insya-Allah', 'Assalamualaikum']],
            ['Apa kita ucap apabila berjumpa kawan Muslim?', 'Assalamualaikum', ['Selamat tinggal', 'Alhamdulillah', 'Astaghfirullah']],
            ['Kita makan dan minum dengan tangan …', 'Kanan', ['Kiri']],
            ['Masuk ke masjid, kita melangkah dengan kaki …', 'Kanan', ['Kiri']],
            ['Masuk ke tandas, kita melangkah dengan kaki …', 'Kiri', ['Kanan']],
            ['Apa kita ucap apabila bersin?', 'Alhamdulillah', ['Bismillah', 'Masya-Allah', 'Insya-Allah']],
            ['Apa kita ucap apabila berjanji membuat sesuatu esok?', 'Insya-Allah', ['Alhamdulillah', 'Bismillah', 'Astaghfirullah']],
            ['Apabila ibu memanggil, kita patut …', 'Segera menyahut', ['Buat-buat tidak dengar', 'Terus bermain']],
            ['Jika kita bersalah kepada kawan, kita patut …', 'Meminta maaf', ['Lari', 'Menyalahkan kawan']],
            ['Apa kita ucap apabila melihat sesuatu yang indah?', 'Masya-Allah', ['Astaghfirullah', 'Bismillah', 'Assalamualaikum']],
            ['Apa kita ucap apabila tersilap membuat sesuatu yang salah?', 'Astaghfirullah', ['Alhamdulillah', 'Insya-Allah', 'Masya-Allah']],
        ];

        if ($older) {
            $questions = array_merge($questions, [
                ['Apa jawapan kepada orang yang bersin dan mengucap Alhamdulillah?', 'Yarhamukallah', ['Waalaikumussalam', 'Amin', 'Insya-Allah']],
                ['Apa jawapan kepada ucapan salam?', 'Waalaikumussalam', ['Assalamualaikum', 'Alhamdulillah', 'Yarhamukallah']],
                ['Apabila berjanji, seorang Muslim mesti …', 'Menunaikan janji', ['Melupakannya', 'Mengubahnya sesuka hati']],
                ['Ketika azan berkumandang, kita patut …', 'Diam dan mendengar', ['Terus bermain', 'Bercakap kuat']],
            ]);
        }

        return array_map(fn (array $q) => ['prompt' => $q[0], 'answer' => $q[1], 'options' => array_merge([$q[1]], $q[2])], $questions);
    }

    /** @return list<string> */
    private static function words(): array
    {
        return ['SOLAT', 'PUASA', 'ZAKAT', 'DOA', 'SABAR', 'JUJUR', 'ADAB', 'SALAM', 'HAJI', 'WUDUK', 'IMAN'];
    }

    /** @return list<string> */
    private static function olderWords(): array
    {
        return ['SYUKUR', 'AMANAH', 'SEDEKAH', 'IKHLAS', 'MASJID', 'TAQWA', 'SABAR', 'JUJUR', 'ZAKAT', 'WUDUK', 'ISTIQAMAH', 'TAWADUK'];
    }
}
