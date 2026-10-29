<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harder kinds of play for the higher game levels (user decision
     * 2026-10-29): "best action" and "find the tempter's sentence" items for
     * Pilih yang Baik, and a clue for each Cari Perkataan word (Sukar shows
     * clues instead of the words).
     */
    public function up(): void
    {
        Schema::table('game_items', function (Blueprint $table) {
            // Pilih yang Baik: null (good or not), "tindakan" or "cerita".
            $table->string('variant', 20)->nullable()->after('age_group');
        });

        $now = now();

        foreach ($this->clues() as $word => $clue) {
            DB::table('game_items')->where('game', 'cari-perkataan')->where('prompt', $word)->whereNull('explanation')
                ->update(['explanation' => $clue, 'updated_at' => $now]);
        }

        if (DB::table('game_items')->whereNotNull('variant')->exists()) {
            return;
        }

        $order = (int) DB::table('game_items')->max('sort_order');
        $rows = [];
        foreach ($this->actions() as [$age, $situation, $best, $wrong, $why]) {
            $rows[] = ['variant' => 'tindakan', 'age_group' => $age, 'prompt' => $situation, 'answer' => $best, 'options' => $wrong, 'explanation' => $why];
        }
        foreach ($this->stories() as [$age, $title, $sentences, $wrongIndex, $why]) {
            $rows[] = ['variant' => 'cerita', 'age_group' => $age, 'prompt' => $title, 'answer' => $sentences[$wrongIndex], 'options' => $sentences, 'explanation' => $why];
        }

        DB::table('game_items')->insert(array_map(fn (array $row) => [
            'game' => 'pilih-yang-baik', 'is_good' => null, 'is_active' => true, 'sort_order' => ++$order,
            'created_at' => $now, 'updated_at' => $now,
        ] + array_merge($row, ['options' => json_encode($row['options'], JSON_UNESCAPED_UNICODE)]), $rows));
    }

    public function down(): void
    {
        DB::table('game_items')->whereNotNull('variant')->delete();
        Schema::table('game_items', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
    }

    /** @return array<string, string> */
    private function clues(): array
    {
        return [
            'SOLAT' => 'Rukun Islam kedua', 'PUASA' => 'Menahan lapar dan dahaga di bulan Ramadan',
            'ZAKAT' => 'Rukun Islam keempat, membersihkan harta', 'DOA' => 'Memohon kepada Allah',
            'SABAR' => 'Tenang ketika diuji', 'JUJUR' => 'Sentiasa bercakap benar',
            'ADAB' => 'Kelakuan yang sopan dan baik', 'SALAM' => 'Ucapan ketika berjumpa',
            'HAJI' => 'Rukun Islam kelima, ke Makkah', 'WUDUK' => 'Bersuci sebelum solat',
            'IMAN' => 'Percaya kepada Allah', 'NABI' => 'Utusan Allah kepada manusia',
            'QURAN' => 'Kitab suci umat Islam', 'ISLAM' => 'Agama kita',
            'MAAF' => 'Dipinta apabila kita bersalah', 'KAABAH' => 'Kiblat umat Islam',
            'SUNAT' => 'Amalan yang mendapat pahala jika dibuat', 'IHSAN' => 'Beribadah seolah-olah melihat Allah',
            'SYUKUR' => 'Berterima kasih atas nikmat Allah', 'AMANAH' => 'Menjaga apa yang dipercayakan',
            'SEDEKAH' => 'Memberi kepada yang memerlukan', 'IKHLAS' => 'Beramal kerana Allah semata-mata',
            'MASJID' => 'Tempat solat berjemaah', 'TAQWA' => 'Taat kepada Allah dan takut melanggar larangan-Nya',
            'ISTIQAMAH' => 'Tetap dan berterusan dalam kebaikan', 'TAWADUK' => 'Rendah diri, tidak sombong',
            'HIJRAH' => 'Perpindahan Nabi ﷺ dari Makkah ke Madinah', 'SAHABAT' => 'Teman setia Nabi ﷺ',
            'TAWAKAL' => 'Berserah kepada Allah selepas berusaha', 'SELAWAT' => 'Doa untuk Nabi Muhammad ﷺ',
            'KHUSYUK' => 'Tenang dan fokus ketika solat', 'ISTIGHFAR' => 'Memohon ampun kepada Allah',
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: list<string>, 4: string}> age, situation, best action, wrong actions, why */
    private function actions(): array
    {
        return [
            ['both', 'Anas terjumpa dompet di padang sekolah. Apa patut dia buat?', 'Serahkan kepada guru', ['Simpan untuk diri sendiri', 'Biarkan sahaja di situ'], 'Barang yang dijumpai mesti dipulangkan kepada pemiliknya. Itu amanah.'],
            ['both', 'Adik Anis menangis kerana mainannya rosak. Apa patut Anis buat?', 'Pujuk adik dan bantu membaikinya', ['Ketawakan adik', 'Buat-buat tidak nampak'], 'Kasih sayang kepada adik membuatkan keluarga bahagia.'],
            ['both', 'Azan Maghrib berkumandang ketika Anas sedang bermain. Apa patut dia buat?', 'Berhenti bermain dan bersiap untuk solat', ['Main sampai habis dahulu', 'Kuatkan suara televisyen'], 'Solat didahulukan, bermain kemudian.'],
            ['both', 'Kawan Anis tertinggal pensel ketika ujian. Apa patut Anis buat?', 'Pinjamkan pensel lebihnya', ['Diam sahaja', 'Ejek kawan itu'], 'Membantu kawan yang dalam kesusahan ialah akhlak yang mulia.'],
            ['both', 'Anas tidak sengaja memecahkan pasu ibu. Apa patut dia buat?', 'Beritahu ibu dan minta maaf', ['Sorokkan serpihan pasu', 'Salahkan kucing'], 'Berkata benar walaupun takut ialah tanda berani dan jujur.'],
            ['both', 'Seorang nenek berdiri di dalam bas yang penuh. Apa patut Anis buat?', 'Beri tempat duduknya kepada nenek', ['Pura-pura tidur', 'Letak beg di kerusi sebelah'], 'Menghormati dan membantu orang tua ialah akhlak seorang Muslim.'],
            ['both', 'Anis mendapat banyak duit raya. Apa yang paling baik?', 'Simpan sebahagian dan sedekah sedikit', ['Habiskan semua untuk gula-gula', 'Menunjuk-nunjuk kepada kawan'], 'Berjimat dan bersedekah membawa keberkatan.'],
            ['both', 'Ada murid baharu duduk seorang diri di kelas Anas. Apa patut Anas buat?', 'Ajak dia bermain bersama', ['Biarkan dia seorang', 'Berbisik tentang dia dengan kawan'], 'Menjadi kawan yang baik membuatkan orang lain gembira.'],
            ['both', 'Kawan-kawan Anis sedang mengumpat tentang orang lain. Apa patut Anis buat?', 'Tegur dengan baik dan tukar topik', ['Ikut mengumpat', 'Ceritakan pula kepada orang lain'], 'Mengumpat dilarang dalam Islam. Jagalah lidah.'],
            ['both', 'Selepas makan di restoran, meja Anas berselerak. Apa patut dia buat?', 'Kumpulkan sisa makanan dengan kemas', ['Tinggalkan sahaja', 'Buang sisa ke lantai'], 'Kebersihan sebahagian daripada iman, di mana-mana sahaja.'],
            ['10-13', 'Anas menerima mesej yang mengaibkan kawan sekelasnya. Apa patut dia buat?', 'Jangan sebarkan dan beritahu guru atau ibu bapa', ['Hantar kepada semua kawan', 'Tambah komen lucu'], 'Menjaga aib orang lain ialah tuntutan agama.'],
            ['10-13', 'Anis mendapat markah rendah dalam ujian. Apa yang paling baik?', 'Muhasabah dan belajar lebih rajin', ['Sorokkan kertas ujian daripada ibu bapa', 'Salahkan guru'], 'Kegagalan ialah peluang untuk memperbaiki diri.'],
            ['10-13', 'Kawan Anas mengajaknya ponteng kelas mengaji. Apa patut dia buat?', 'Menolak dengan baik dan ajak kawan ke kelas', ['Ikut sahaja', 'Ponteng tanpa memberitahu sesiapa'], 'Kawan yang baik membawa kita kepada kebaikan, bukan sebaliknya.'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: list<string>, 3: int, 4: string}> age, title, sentences, index of the tempter's sentence, why */
    private function stories(): array
    {
        return [
            ['both', 'Pagi Anas', ['Anas bangun awal dan menunaikan solat Subuh.', 'Dia mengambil duit dalam dompet ayah tanpa izin.', 'Dia menolong ibu menyusun pinggan.', 'Dia memberi salam sebelum ke sekolah.'], 1, 'Mengambil tanpa izin ialah mencuri, walaupun daripada keluarga sendiri.'],
            ['both', 'Di kantin', ['Anis beratur dengan sabar.', 'Dia membaca Bismillah sebelum makan.', 'Dia membuang bekas makanan ke dalam tong sampah.', 'Dia makan sambil berlari ke sana ke mari.'], 3, 'Kita makan sambil duduk dengan tenang.'],
            ['both', 'Di masjid', ['Anas bercakap kuat ketika orang sedang solat.', 'Dia masuk dengan kaki kanan.', 'Dia duduk diam mendengar khutbah.', 'Dia menyusun selipar dengan kemas.'], 0, 'Kita menjaga ketenangan di masjid supaya orang dapat beribadah.'],
            ['both', 'Hari hujan', ['Anis membawa payung ke sekolah.', 'Dia berkongsi payung dengan kawan.', 'Dia sengaja memercikkan air lopak kepada kawan.', 'Dia bersyukur kerana hujan menyuburkan tanaman.'], 2, 'Mengusik sehingga menyusahkan kawan bukan akhlak yang baik.'],
            ['both', 'Bermain di taman', ['Anas bermain bola bersama kawan-kawan.', 'Dia mengambil giliran dengan adil.', 'Dia minum air apabila dahaga.', 'Dia memarahi kawan yang kalah.'], 3, 'Kalah menang adat permainan. Hormatilah kawan.'],
            ['both', 'Di rumah nenek', ['Anis mencium tangan nenek.', 'Dia bermain telefon ketika nenek bercerita.', 'Dia menolong nenek menyiram pokok.', 'Dia mendoakan kesihatan nenek.'], 1, 'Dengar dan hormati orang tua ketika mereka bercakap.'],
            ['both', 'Di kedai', ['Anas memilih barang yang perlu sahaja.', 'Dia beratur di kaunter.', 'Juruwang tersilap memberi baki lebih, tetapi Anas diam sahaja.', 'Dia mengucapkan terima kasih kepada juruwang.'], 2, 'Baki yang berlebihan mesti dipulangkan. Itu jujur dan amanah.'],
            ['10-13', 'Kerja kumpulan', ['Anas membahagikan tugas dengan adil.', 'Dia mendengar pendapat setiap ahli.', 'Dia menyiapkan bahagiannya tepat pada masanya.', 'Dia menulis namanya sahaja pada hasil kerja kawan-kawan.'], 3, 'Mengaku hasil kerja orang lain sebagai milik sendiri ialah tidak jujur.'],
            ['10-13', 'Di media sosial', ['Anis menyebarkan gambar kawan tanpa izin.', 'Dia berkongsi ilmu yang bermanfaat.', 'Dia menyemak berita sebelum berkongsi.', 'Dia menulis komen yang sopan.'], 0, 'Gambar orang lain dikongsi hanya dengan izinnya.'],
        ];
    }
};
