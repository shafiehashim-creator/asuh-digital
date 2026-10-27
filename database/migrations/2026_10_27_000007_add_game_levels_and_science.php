<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Game levels (Mudah, Sederhana, Sukar; user decision 2026-10-27): the
     * highest level each child has opened per game, and the starting
     * questions for the new science game.
     */
    public function up(): void
    {
        Schema::create('game_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('game', 40);
            $table->unsignedTinyInteger('unlocked')->default(1);
            $table->timestamps();
            $table->unique(['child_id', 'game']);
        });

        if (DB::table('game_items')->where('game', 'sains')->exists()) {
            return;
        }

        $now = now();
        $order = (int) DB::table('game_items')->max('sort_order');
        $rows = [];
        foreach ($this->science() as [$age, $question, $answer, $wrong]) {
            $rows[] = [
                'game' => 'sains', 'age_group' => $age, 'prompt' => $question, 'answer' => $answer,
                'is_good' => null, 'explanation' => null, 'options' => json_encode($wrong, JSON_UNESCAPED_UNICODE),
                'is_active' => true, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('game_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_levels');
        DB::table('game_items')->where('game', 'sains')->delete();
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: list<string>}> age, question, answer, wrong answers */
    private function science(): array
    {
        return [
            ['both', '🐔 Haiwan manakah yang bertelur?', 'Ayam', ['Kucing', 'Lembu', 'Kambing']],
            ['both', '🐱 Haiwan manakah yang menyusukan anaknya?', 'Kucing', ['Ayam', 'Ikan', 'Penyu']],
            ['both', '🌱 Tumbuhan memerlukan apa untuk membuat makanan?', 'Cahaya matahari', ['Gula-gula', 'Bantal', 'Kasut']],
            ['both', '🌳 Tumbuhan menyerap air melalui …', 'Akar', ['Bunga', 'Buah', 'Daun']],
            ['both', '🕷️ Berapakah bilangan kaki labah-labah?', '8', ['6', '4', '10']],
            ['both', '🐜 Berapakah bilangan kaki semut?', '6', ['8', '4', '2']],
            ['both', '❤️ Organ manakah yang mengepam darah ke seluruh badan?', 'Jantung', ['Paru-paru', 'Perut', 'Otak']],
            ['both', '🫁 Kita bernafas menggunakan …', 'Paru-paru', ['Jantung', 'Perut', 'Tulang']],
            ['both', '👃 Hidung digunakan untuk deria …', 'Bau', ['Rasa', 'Dengar', 'Sentuh']],
            ['both', '👅 Lidah digunakan untuk deria …', 'Rasa', ['Bau', 'Lihat', 'Dengar']],
            ['both', '♨️ Air yang dididihkan bertukar menjadi …', 'Wap', ['Ais', 'Batu', 'Pasir']],
            ['both', '🧊 Air yang dibekukan menjadi …', 'Ais', ['Wap', 'Minyak', 'Pasir']],
            ['both', '🌍 Planet tempat kita tinggal ialah …', 'Bumi', ['Marikh', 'Musytari', 'Zuhal']],
            ['both', '☀️ Bintang yang paling dekat dengan Bumi ialah …', 'Matahari', ['Bulan', 'Marikh', 'Zuhrah']],
            ['both', '🌙 Bulan bercahaya kerana memantulkan cahaya …', 'Matahari', ['Lampu', 'Api', 'Kilat']],
            ['both', '🐟 Ikan bernafas di dalam air menggunakan …', 'Insang', ['Paru-paru', 'Sirip', 'Ekor']],
            ['both', '🐸 Anak katak dipanggil …', 'Berudu', ['Ulat', 'Anak ayam', 'Kepompong']],
            ['both', '🦋 Rama-rama berasal daripada …', 'Ulat beluncas', ['Berudu', 'Telur ikan', 'Biji benih']],
            ['both', '🧲 Magnet boleh menarik …', 'Paku besi', ['Kertas', 'Kayu', 'Plastik']],
            ['both', '💧 Benda manakah yang terapung di atas air?', 'Gabus', ['Batu', 'Paku', 'Duit syiling']],
            ['both', '🌈 Pelangi selalu kelihatan selepas …', 'Hujan', ['Ribut pasir', 'Salji', 'Gerhana']],
            ['both', '🐄 Lembu makan …', 'Rumput', ['Daging', 'Ikan', 'Batu']],
            ['10-13', '🍃 Proses tumbuhan membuat makanan sendiri dipanggil …', 'Fotosintesis', ['Respirasi', 'Penyejatan', 'Pendebungaan']],
            ['10-13', '🌬️ Gas yang kita sedut ketika bernafas ialah …', 'Oksigen', ['Karbon dioksida', 'Nitrogen', 'Helium']],
            ['10-13', '😮‍💨 Gas yang kita hembus keluar ketika bernafas ialah …', 'Karbon dioksida', ['Oksigen', 'Hidrogen', 'Helium']],
            ['10-13', '🪐 Planet yang paling besar dalam Sistem Suria ialah …', 'Musytari', ['Bumi', 'Marikh', 'Utarid']],
            ['10-13', '☄️ Planet yang paling dekat dengan Matahari ialah …', 'Utarid', ['Zuhrah', 'Bumi', 'Marikh']],
            ['10-13', '🌏 Siang dan malam berlaku kerana Bumi …', 'Berputar pada paksinya', ['Mengelilingi Bulan', 'Berhenti bergerak', 'Bertukar warna']],
            ['10-13', '📅 Bumi mengelilingi Matahari sekali dalam masa kira-kira …', 'Setahun', ['Sehari', 'Seminggu', 'Sebulan']],
            ['10-13', '💀 Tulang yang melindungi otak ialah …', 'Tengkorak', ['Tulang rusuk', 'Tulang belakang', 'Tulang paha']],
            ['10-13', '🦁 Haiwan yang makan daging sahaja dipanggil …', 'Karnivor', ['Herbivor', 'Omnivor']],
            ['10-13', '🐐 Haiwan yang makan tumbuhan sahaja dipanggil …', 'Herbivor', ['Karnivor', 'Omnivor']],
            ['10-13', '☁️ Air laut menyejat, membentuk awan dan turun sebagai hujan. Ini dipanggil …', 'Kitaran air', ['Kitaran hidup', 'Gerhana', 'Fotosintesis']],
            ['10-13', '🌑 Gerhana bulan berlaku apabila … berada di antara Matahari dan Bulan.', 'Bumi', ['Marikh', 'Awan', 'Bintang']],
            ['10-13', '💡 Bahan manakah yang mengalirkan elektrik dengan baik?', 'Kuprum', ['Getah', 'Kayu', 'Plastik']],
        ];
    }
};
