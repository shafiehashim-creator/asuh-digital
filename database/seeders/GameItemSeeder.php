<?php

namespace Database\Seeders;

use App\Enums\Game;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ASUH's starting content for the Mod Anak games, loaded once by the
 * game_items migration (never over content the admin has already written).
 * Basics taught in Malaysian schools; checked by the syariah panel before
 * the games are switched on.
 */
class GameItemSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('game_items')->exists()) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($this->rows() as $i => $row) {
            $rows[] = $row + [
                'answer' => null, 'is_good' => null, 'explanation' => null, 'options' => null,
                'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('game_items')->insert($chunk);
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->memoryPairs() as [$emoji, $word]) {
            $rows[] = ['game' => Game::Memory->value, 'age_group' => 'both', 'prompt' => $emoji, 'answer' => $word];
        }

        foreach ($this->scenarios() as [$age, $text, $good, $why]) {
            $rows[] = ['game' => Game::GoodChoice->value, 'age_group' => $age, 'prompt' => $text, 'is_good' => $good, 'explanation' => $why];
        }

        foreach ($this->orderSets() as [$age, $title, $items]) {
            $rows[] = ['game' => Game::Order->value, 'age_group' => $age, 'prompt' => $title, 'options' => json_encode($items, JSON_UNESCAPED_UNICODE)];
        }

        foreach ($this->words() as [$age, $word]) {
            $rows[] = ['game' => Game::WordSearch->value, 'age_group' => $age, 'prompt' => $word];
        }

        foreach ($this->prophets() as [$age, $clue, $name]) {
            $rows[] = ['game' => Game::Prophets->value, 'age_group' => $age, 'prompt' => $clue, 'answer' => $name];
        }

        foreach ($this->manners() as [$age, $question, $answer, $wrong]) {
            $rows[] = ['game' => Game::QuickQuiz->value, 'age_group' => $age, 'prompt' => $question, 'answer' => $answer, 'options' => json_encode($wrong, JSON_UNESCAPED_UNICODE)];
        }

        return $rows;
    }

    /** @return list<array{0: string, 1: string}> */
    private function memoryPairs(): array
    {
        return [
            ['🕌', 'Masjid'], ['📖', 'Al-Quran'], ['🕋', 'Kaabah'], ['📿', 'Tasbih'],
            ['🌙', 'Bulan sabit'], ['🌴', 'Pokok kurma'], ['🤲', 'Berdoa'], ['🐪', 'Unta'],
            ['🍯', 'Madu'], ['🫒', 'Zaitun'], ['🍇', 'Anggur'], ['🐑', 'Kambing'],
            ['🐄', 'Lembu'], ['🕊️', 'Burung merpati'], ['💧', 'Air'], ['☀️', 'Matahari'],
            ['⭐', 'Bintang'], ['🪔', 'Pelita'], ['🤝', 'Bersalam'], ['🧕', 'Tudung'],
            ['📚', 'Buku'], ['🎁', 'Hadiah'], ['🧼', 'Sabun'], ['🌧️', 'Hujan'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: bool, 3: string}> age, scenario, good, why */
    private function scenarios(): array
    {
        return [
            ['both', 'Anis membantu ibu mengemas meja selepas makan.', true, 'Membantu ibu bapa ialah akhlak yang mulia.'],
            ['both', 'Anas memberi salam ketika masuk ke rumah.', true, 'Memberi salam membawa kebaikan kepada seisi rumah.'],
            ['both', 'Anis berkongsi bekal dengan kawan yang terlupa membawa makanan.', true, 'Berkongsi tanda kita sayang kepada kawan.'],
            ['both', 'Anas membaca Bismillah sebelum makan.', true, 'Kita mulakan setiap perkara baik dengan nama Allah.'],
            ['both', 'Anis memulangkan pensel yang dipinjam daripada kawan.', true, 'Memulangkan barang pinjaman ialah sifat amanah.'],
            ['both', 'Anas meminta maaf selepas terlanggar kawan.', true, 'Meminta maaf menjaga hati kawan.'],
            ['both', 'Anis berkata benar walaupun dia tersilap.', true, 'Jujur itu berani, dan Allah suka orang yang jujur.'],
            ['both', 'Anas membuang sampah ke dalam tong sampah.', true, 'Menjaga kebersihan sebahagian daripada iman.'],
            ['both', 'Anis berterima kasih apabila diberi hadiah.', true, 'Berterima kasih tanda kita menghargai orang lain.'],
            ['both', 'Anas menolong kawan yang terjatuh di padang.', true, 'Menolong orang yang dalam kesusahan ialah perbuatan yang disukai Allah.'],
            ['both', 'Anis menyiram pokok bunga di halaman rumah.', true, 'Menjaga tumbuhan tanda kita bersyukur dengan nikmat Allah.'],
            ['both', 'Anas mengemas mainan selepas bermain.', true, 'Mengemas selepas bermain menjadikan rumah kemas dan ibu gembira.'],
            ['both', 'Anis membaca doa sebelum tidur.', true, 'Berdoa sebelum tidur ialah amalan yang baik.'],
            ['both', 'Anas tidur awal supaya dapat bangun untuk solat Subuh.', true, 'Menjaga solat ialah tanda kita taat kepada Allah.'],
            ['both', 'Anas mengambil gula-gula kawan tanpa izin.', false, 'Kita mesti meminta izin dahulu sebelum mengambil barang orang.'],
            ['both', 'Anis mengejek kawan yang terjatuh.', false, 'Mengejek menyakitkan hati. Bantulah kawan bangun.'],
            ['both', 'Anas menjerit kepada adik kerana tidak mahu berkongsi mainan.', false, 'Bercakaplah dengan lembut dan berkongsilah dengan adik.'],
            ['both', 'Anis berbohong kepada ibu tentang kerja sekolah.', false, 'Berbohong ialah bisikan penggoda. Berkata benarlah.'],
            ['both', 'Anas terus bermain dan tidak menyahut apabila ayah memanggil.', false, 'Apabila ibu bapa memanggil, segeralah menyahut.'],
            ['both', 'Anis membuang sampah ke dalam longkang.', false, 'Sampah dibuang ke dalam tong supaya alam kekal bersih.'],
            ['both', 'Anas bercakap ketika guru sedang mengajar.', false, 'Kita menghormati guru dengan mendengar ketika guru mengajar.'],
            ['both', 'Anis membiarkan paip air terbuka selepas berwuduk.', false, 'Jangan membazir air, walaupun ketika berwuduk.'],
            ['both', 'Anas memijak bunga di taman sesuka hati.', false, 'Sayangilah tumbuhan ciptaan Allah.'],
            ['both', 'Anis menolak kawan supaya dapat beratur di depan.', false, 'Beratur dengan sabar dan jangan menolak orang lain.'],
            ['both', 'Anas mengejar dan memukul kucing dengan kayu.', false, 'Kita mesti menyayangi haiwan dan tidak menyakitinya.'],
            ['10-13', 'Anas meniru jawapan kawan semasa ujian.', false, 'Meniru ialah tidak jujur. Usaha sendiri lebih berkat.'],
            ['10-13', 'Anis menyebarkan cerita buruk tentang kawan dalam kumpulan mesej kelas.', false, 'Mengumpat dan menyebar aib orang ialah dosa. Jagalah lidah dan jari.'],
            ['10-13', 'Anas menegur kawannya dengan baik apabila kawan itu membuli orang lain.', true, 'Mencegah kemungkaran dengan cara yang baik ialah tanda keberanian.'],
            ['10-13', 'Anis menyimpan baki duit belanja untuk bersedekah.', true, 'Sedekah tidak mengurangkan harta, malah membawa keberkatan.'],
            ['10-13', 'Anas terus bermain permainan telefon sehingga lewat menunaikan solat.', false, 'Solat didahulukan, bermain kemudian.'],
            ['10-13', 'Anis menolong jiran tua membawa barang dari kereta.', true, 'Berbuat baik kepada jiran dan orang tua ialah akhlak seorang Muslim.'],
            ['10-13', 'Anas menyimpan rahsia yang diamanahkan oleh kawannya.', true, 'Menjaga rahsia ialah sifat amanah.'],
            ['10-13', 'Anis menziarahi kawan yang sakit di rumahnya.', true, 'Menziarahi orang sakit ialah amalan yang dituntut dalam Islam.'],
            ['10-13', 'Anas bermain permainan video secara senyap selepas ibu bapanya melarang.', false, 'Taat kepada ibu bapa dalam perkara kebaikan ialah kewajipan anak.'],
            ['10-13', 'Anis mengambil makanan yang banyak di majlis tetapi tidak menghabiskannya.', false, 'Islam melarang pembaziran. Ambil sekadar yang boleh dihabiskan.'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: list<string>}> age, title, items in order */
    private function orderSets(): array
    {
        return [
            ['both', 'Rukun Islam', ['Mengucap dua kalimah syahadah', 'Menunaikan solat', 'Berpuasa di bulan Ramadan', 'Menunaikan zakat', 'Menunaikan haji bagi yang mampu']],
            ['both', 'Solat fardu ikut waktu', ['Subuh', 'Zohor', 'Asar', 'Maghrib', 'Isyak']],
            ['both', 'Rukun Iman', ['Beriman kepada Allah', 'Beriman kepada malaikat', 'Beriman kepada kitab-kitab', 'Beriman kepada rasul-rasul', 'Beriman kepada hari akhirat', 'Beriman kepada qada dan qadar']],
            ['both', 'Adab makan', ['Membasuh tangan', 'Membaca Bismillah dan doa makan', 'Makan dengan tangan kanan', 'Mengucap Alhamdulillah selepas makan']],
            ['10-13', 'Fardu wuduk', ['Niat', 'Membasuh muka', 'Membasuh kedua-dua tangan hingga siku', 'Menyapu sebahagian kepala', 'Membasuh kedua-dua kaki hingga buku lali', 'Tertib']],
            ['10-13', 'Bulan dalam kalendar Hijrah', ['Muharam', 'Safar', 'Rabiulawal', 'Rabiulakhir', 'Jamadilawal', 'Jamadilakhir', 'Rejab', 'Syaaban', 'Ramadan', 'Syawal', 'Zulkaedah', 'Zulhijah']],
            ['10-13', 'Lima nabi yang pertama', ['Nabi Adam', 'Nabi Idris', 'Nabi Nuh', 'Nabi Hud', 'Nabi Saleh']],
        ];
    }

    /** @return list<array{0: string, 1: string}> age, word */
    private function words(): array
    {
        $younger = ['SOLAT', 'PUASA', 'ZAKAT', 'DOA', 'SABAR', 'JUJUR', 'ADAB', 'SALAM', 'HAJI', 'WUDUK', 'IMAN', 'NABI', 'QURAN', 'ISLAM', 'MAAF', 'KAABAH', 'SUNAT', 'IHSAN'];
        $older = ['SYUKUR', 'AMANAH', 'SEDEKAH', 'IKHLAS', 'MASJID', 'TAQWA', 'ISTIQAMAH', 'TAWADUK', 'HIJRAH', 'SAHABAT', 'TAWAKAL', 'SELAWAT', 'KHUSYUK', 'ISTIGHFAR'];

        return array_merge(
            array_map(fn ($word) => ['both', $word], $younger),
            array_map(fn ($word) => ['10-13', $word], $older),
        );
    }

    /** @return list<array{0: string, 1: string, 2: string}> age, clue, prophet */
    private function prophets(): array
    {
        return [
            ['both', 'Manusia pertama yang diciptakan Allah.', 'Nabi Adam'],
            ['both', 'Membina bahtera besar dengan perintah Allah.', 'Nabi Nuh'],
            ['both', 'Api tidak membakarnya dengan izin Allah.', 'Nabi Ibrahim'],
            ['both', 'Pernah ditelan oleh ikan besar.', 'Nabi Yunus'],
            ['both', 'Laut terbelah apabila baginda memukulnya dengan tongkat, dengan izin Allah.', 'Nabi Musa'],
            ['both', 'Memahami bahasa haiwan, termasuk semut.', 'Nabi Sulaiman'],
            ['both', 'Dicampak ke dalam perigi oleh abang-abangnya.', 'Nabi Yusuf'],
            ['both', 'Nabi dan rasul terakhir yang menerima Al-Quran.', 'Nabi Muhammad ﷺ'],
            ['both', 'Boleh bercakap ketika masih bayi dengan izin Allah.', 'Nabi Isa'],
            ['both', 'Menerima kitab Zabur dan mempunyai suara yang merdu.', 'Nabi Daud'],
            ['both', 'Air zamzam memancar berhampirannya ketika baginda masih kecil.', 'Nabi Ismail'],
            ['10-13', 'Sangat sabar ketika diuji dengan penyakit yang lama.', 'Nabi Ayub'],
            ['10-13', 'Mukjizatnya seekor unta betina yang keluar dari batu.', 'Nabi Saleh'],
            ['10-13', 'Berdoa memohon anak walaupun sudah tua, lalu dikurniakan Nabi Yahya.', 'Nabi Zakaria'],
            ['10-13', 'Kaumnya, kaum Ad, dibinasakan oleh angin yang sangat kencang.', 'Nabi Hud'],
            ['10-13', 'Menyeru kaumnya supaya jujur ketika menimbang dan menyukat.', 'Nabi Syuaib'],
            ['10-13', 'Saudara Nabi Musa yang membantunya berdakwah kepada Firaun.', 'Nabi Harun'],
            ['10-13', 'Nabi yang mula-mula menulis dengan pena.', 'Nabi Idris'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: list<string>}> age, question, answer, wrong answers */
    private function manners(): array
    {
        return [
            ['both', 'Apa kita ucap sebelum makan?', 'Bismillah', ['Alhamdulillah', 'Assalamualaikum', 'Subhanallah']],
            ['both', 'Apa kita ucap selepas makan?', 'Alhamdulillah', ['Bismillah', 'Insya-Allah', 'Assalamualaikum']],
            ['both', 'Apa kita ucap apabila berjumpa kawan Muslim?', 'Assalamualaikum', ['Selamat tinggal', 'Alhamdulillah', 'Astaghfirullah']],
            ['both', 'Kita makan dan minum dengan tangan …', 'Kanan', ['Kiri']],
            ['both', 'Masuk ke masjid, kita melangkah dengan kaki …', 'Kanan', ['Kiri']],
            ['both', 'Masuk ke tandas, kita melangkah dengan kaki …', 'Kiri', ['Kanan']],
            ['both', 'Apa kita ucap apabila bersin?', 'Alhamdulillah', ['Bismillah', 'Masya-Allah', 'Insya-Allah']],
            ['both', 'Apa kita ucap apabila berjanji membuat sesuatu esok?', 'Insya-Allah', ['Alhamdulillah', 'Bismillah', 'Astaghfirullah']],
            ['both', 'Apabila ibu memanggil, kita patut …', 'Segera menyahut', ['Buat-buat tidak dengar', 'Terus bermain']],
            ['both', 'Jika kita bersalah kepada kawan, kita patut …', 'Meminta maaf', ['Lari', 'Menyalahkan kawan']],
            ['both', 'Apa kita ucap apabila melihat sesuatu yang indah?', 'Masya-Allah', ['Astaghfirullah', 'Bismillah', 'Assalamualaikum']],
            ['both', 'Apa kita ucap apabila tersilap membuat sesuatu yang salah?', 'Astaghfirullah', ['Alhamdulillah', 'Insya-Allah', 'Masya-Allah']],
            ['both', 'Apabila masuk ke rumah, kita …', 'Memberi salam', ['Menjerit', 'Terus berlari masuk']],
            ['both', 'Sebelum tidur, kita …', 'Membaca doa tidur', ['Bermain telefon', 'Makan gula-gula']],
            ['both', 'Apabila kawan memberi hadiah, kita ucap …', 'Terima kasih', ['Mana lagi?', 'Tak nak']],
            ['both', 'Apabila bercakap dengan orang tua, kita …', 'Bercakap dengan lembut', ['Meninggikan suara', 'Membelakangkan mereka']],
            ['both', 'Sebaik-baiknya kita minum air sambil …', 'Duduk', ['Berlari', 'Melompat']],
            ['10-13', 'Apa jawapan kepada orang yang bersin dan mengucap Alhamdulillah?', 'Yarhamukallah', ['Waalaikumussalam', 'Amin', 'Insya-Allah']],
            ['10-13', 'Apa jawapan kepada ucapan salam?', 'Waalaikumussalam', ['Assalamualaikum', 'Alhamdulillah', 'Yarhamukallah']],
            ['10-13', 'Apabila berjanji, seorang Muslim mesti …', 'Menunaikan janji', ['Melupakannya', 'Mengubahnya sesuka hati']],
            ['10-13', 'Ketika azan berkumandang, kita patut …', 'Diam dan mendengar', ['Terus bermain', 'Bercakap kuat']],
            ['10-13', 'Apa kita ucap apabila mendengar nama Nabi Muhammad?', 'Sallallahu alaihi wasallam', ['Subhanallah', 'Astaghfirullah', 'Insya-Allah']],
            ['10-13', 'Apa kita ucap apabila mendengar berita kematian?', 'Inna lillahi wa inna ilaihi raji‘un', ['Alhamdulillah', 'Masya-Allah', 'Bismillah']],
        ];
    }
}
