<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The yearly subscription now includes the animated videos (2026-10-27),
     * so the landing headline, subheadline and FAQ say so. Only texts still
     * at their old default change; anything the admin wrote is kept.
     */
    public function up(): void
    {
        foreach ($this->changes() as $key => [$old, $new]) {
            $row = DB::table('settings')->where('key', $key)->first();

            if ($row && json_decode($row->value, true) === $old) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($new), 'updated_at' => now()]);
            }
        }

        // Settings are cached forever (App\Models\Setting); drop the copy so the new texts show.
        Cache::forget('asuh.settings');
    }

    public function down(): void
    {
        // The old texts stay in changes(); restoring them by hand is safer than guessing.
    }

    /** @return array<string, array{0: mixed, 1: mixed}> old default => new default */
    private function changes(): array
    {
        $new = config('asuh.settings');

        return [
            'landing_headline' => ['Rak buku Islamik digital untuk anak 6–13 tahun', $new['landing_headline']],
            'landing_subheadline' => ['Komik, kisah nabi, akhlak dan doa harian yang telah disemak syariah. Jom Jadi Baik!', $new['landing_subheadline']],
            'landing_headline_en' => ['A digital Islamic bookshelf for children aged 6–13', $new['landing_headline_en']],
            'landing_subheadline_en' => ['Comics, stories of the prophets, good character and daily prayers, all syariah-reviewed. Jom Jadi Baik!', $new['landing_subheadline_en']],
            'landing_faq' => [$this->oldFaq($new['landing_faq'], 'Adakah video animasi termasuk?', [
                0 => 'Rak buku Islamik digital daripada ASUH, jenama Galeri Ilmu Media Group, untuk kanak-kanak berumur 6 hingga 13 tahun. Anak boleh membaca komik, kisah nabi, buku akhlak, doa harian dan sirah di telefon, tablet atau komputer.',
                2 => 'Ya. Setiap buku mesti lulus semakan syariah sebelum diterbitkan di ASUH Digital.',
            ]), $new['landing_faq']],
            'landing_faq_en' => [$this->oldFaq($new['landing_faq_en'], 'Are the animated videos included?', [
                0 => 'A digital Islamic bookshelf from ASUH, a Galeri Ilmu Media Group brand, for children aged 6 to 13. Children can read comics, stories of the prophets, books on good character, daily prayers and sirah on a phone, tablet or computer.',
                2 => 'Yes. Every book must pass a syariah review before it is published on ASUH Digital.',
            ]), $new['landing_faq_en']],
        ];
    }

    /**
     * The FAQ as it was: without the new video question, with the old answers.
     *
     * @param  list<array{question: string, answer: string}>  $faq
     * @param  array<int, string>  $answers  old answers by position in the old list
     */
    private function oldFaq(array $faq, string $newQuestion, array $answers): array
    {
        $old = array_values(array_filter($faq, fn (array $item) => $item['question'] !== $newQuestion));

        foreach ($answers as $i => $answer) {
            $old[$i]['answer'] = $answer;
        }

        return $old;
    }
};
