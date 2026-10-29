<?php

namespace App\Support;

/**
 * Sains Ciptaan Allah beyond the admin's question pool: "which one is not …"
 * (Sederhana) and quick sorting into groups (Sukar), made fresh each game.
 */
class ScienceQuestions
{
    /**
     * Groups for "which one is not …", with members only older children
     * meet (the surprising ones, such as the whale and the penguin).
     *
     * @var array<string, array{label: string, members: list<string>, older: list<string>}>
     */
    private const GROUPS = [
        'serangga' => ['label' => 'serangga', 'members' => ['🐜 Semut', '🐝 Lebah', '🦋 Rama-rama', '🦗 Belalang', '🐞 Kumbang'], 'older' => []],
        'mamalia' => ['label' => 'mamalia (menyusukan anak)', 'members' => ['🐱 Kucing', '🐄 Lembu', '🐐 Kambing', '🐘 Gajah'], 'older' => ['🐋 Ikan paus', '🦇 Kelawar']],
        'burung' => ['label' => 'burung', 'members' => ['🐔 Ayam', '🦆 Itik', '🦉 Burung hantu', '🦅 Helang'], 'older' => ['🐧 Penguin']],
        'planet' => ['label' => 'planet', 'members' => ['🌍 Bumi', '🔴 Marikh', '🪐 Zuhal', '🟠 Musytari'], 'older' => ['⚪ Utarid', '🟡 Zuhrah']],
        'deria' => ['label' => 'organ deria', 'members' => ['👁️ Mata', '👂 Telinga', '👃 Hidung', '👅 Lidah'], 'older' => ['✋ Kulit']],
        'buah' => ['label' => 'buah-buahan', 'members' => ['🍎 Epal', '🍌 Pisang', '🍉 Tembikai', '🥭 Mangga', '🍇 Anggur'], 'older' => []],
    ];

    /** Things that clearly belong to none of the groups above. */
    private const OUTSIDERS = [
        'serangga' => ['🐟 Ikan', '🐸 Katak', '🐦 Burung'],
        'mamalia' => ['🐔 Ayam', '🐢 Penyu', '🐍 Ular', '🐟 Ikan'],
        'burung' => ['🦇 Kelawar', '🦋 Rama-rama', '🐱 Kucing'],
        'planet' => ['🌙 Bulan', '☀️ Matahari', '☄️ Komet'],
        'deria' => ['❤️ Jantung', '🫁 Paru-paru', '🦴 Tulang'],
        'buah' => ['🥕 Lobak merah', '🍞 Roti', '🥚 Telur'],
    ];

    /** @var array<string, array{bins: list<string>, items: array<string, string>, older: bool}> */
    private const SORTS = [
        'makan' => ['bins' => ['Karnivor', 'Herbivor'], 'older' => false, 'items' => [
            '🦁 Singa' => 'Karnivor', '🐯 Harimau' => 'Karnivor', '🦅 Helang' => 'Karnivor', '🐊 Buaya' => 'Karnivor',
            '🐄 Lembu' => 'Herbivor', '🐐 Kambing' => 'Herbivor', '🐇 Arnab' => 'Herbivor', '🦒 Zirafah' => 'Herbivor', '🐘 Gajah' => 'Herbivor',
        ]],
        'jirim' => ['bins' => ['Pepejal', 'Cecair', 'Gas'], 'older' => false, 'items' => [
            '🪨 Batu' => 'Pepejal', '🧊 Ais' => 'Pepejal', '🪵 Kayu' => 'Pepejal', '🥄 Sudu' => 'Pepejal',
            '💧 Air' => 'Cecair', '🥛 Susu' => 'Cecair', '🍯 Madu' => 'Cecair', '🫗 Minyak masak' => 'Cecair',
            '♨️ Wap air' => 'Gas', '🌬️ Udara' => 'Gas', '🫧 Oksigen' => 'Gas',
        ]],
        'apung' => ['bins' => ['Terapung', 'Tenggelam'], 'older' => false, 'items' => [
            '🍾 Gabus' => 'Terapung', '🍂 Daun kering' => 'Terapung', '⚽ Bola plastik' => 'Terapung', '🦆 Itik getah' => 'Terapung',
            '🪨 Batu' => 'Tenggelam', '🔩 Paku besi' => 'Tenggelam', '🪙 Duit syiling' => 'Tenggelam', '🔑 Kunci' => 'Tenggelam',
        ]],
        'hidup' => ['bins' => ['Benda hidup', 'Benda bukan hidup'], 'older' => false, 'items' => [
            '🌳 Pokok' => 'Benda hidup', '🐱 Kucing' => 'Benda hidup', '🍄 Cendawan' => 'Benda hidup', '🐛 Ulat' => 'Benda hidup',
            '🪨 Batu' => 'Benda bukan hidup', '🚗 Kereta' => 'Benda bukan hidup', '🪑 Kerusi' => 'Benda bukan hidup', '☁️ Awan' => 'Benda bukan hidup', '🤖 Robot' => 'Benda bukan hidup',
        ]],
        'magnet' => ['bins' => ['Ditarik magnet', 'Tidak ditarik'], 'older' => false, 'items' => [
            '🔩 Paku besi' => 'Ditarik magnet', '📎 Klip kertas besi' => 'Ditarik magnet', '🪡 Jarum' => 'Ditarik magnet',
            '📄 Kertas' => 'Tidak ditarik', '🪵 Kayu' => 'Tidak ditarik', '🧸 Plastik' => 'Tidak ditarik', '🎈 Getah' => 'Tidak ditarik',
        ]],
        'tulang' => ['bins' => ['Vertebrata (bertulang belakang)', 'Invertebrata (tiada tulang belakang)'], 'older' => true, 'items' => [
            '🐟 Ikan' => 'Vertebrata (bertulang belakang)', '🐸 Katak' => 'Vertebrata (bertulang belakang)', '🐍 Ular' => 'Vertebrata (bertulang belakang)', '🐦 Burung' => 'Vertebrata (bertulang belakang)',
            '🐌 Siput' => 'Invertebrata (tiada tulang belakang)', '🪱 Cacing' => 'Invertebrata (tiada tulang belakang)', '🦑 Sotong' => 'Invertebrata (tiada tulang belakang)', '🪼 Obor-obor' => 'Invertebrata (tiada tulang belakang)', '🕷️ Labah-labah' => 'Invertebrata (tiada tulang belakang)',
        ]],
        'elektrik' => ['bins' => ['Konduktor', 'Penebat'], 'older' => true, 'items' => [
            '🟫 Kuprum' => 'Konduktor', '🔩 Besi' => 'Konduktor', '🥫 Aluminium' => 'Konduktor',
            '🎈 Getah' => 'Penebat', '🧸 Plastik' => 'Penebat', '🪵 Kayu kering' => 'Penebat', '🥛 Kaca' => 'Penebat',
        ]],
    ];

    /** @return list<array<string, mixed>> "Which one is not …?" */
    public static function oddOneOut(bool $older, int $count): array
    {
        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $key = array_rand(self::GROUPS);
            $group = self::GROUPS[$key];
            $members = collect($group['members'])->merge($older ? $group['older'] : [])->shuffle()->take(3);
            $outsider = collect(self::OUTSIDERS[$key])->random();

            $questions[] = [
                'prompt' => "Yang manakah BUKAN {$group['label']}?",
                'big' => null,
                'answer' => $outsider,
                'options' => $members->prepend($outsider)->values()->all(),
            ];
        }

        return self::unique($questions);
    }

    /** @return list<array<string, mixed>> quick sorting: each thing into its group */
    public static function sorting(bool $older, int $count): array
    {
        $sorts = array_filter(self::SORTS, fn (array $sort) => $older || ! $sort['older']);
        $questions = [];
        foreach ($sorts as $sort) {
            foreach ($sort['items'] as $thing => $bin) {
                $questions[] = [
                    'prompt' => 'Asingkan dengan pantas!',
                    'big' => $thing,
                    'answer' => $bin,
                    'options' => $sort['bins'],
                    'keepOrder' => true,
                ];
            }
        }

        return collect($questions)->shuffle()->take($count)->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private static function unique(array $questions): array
    {
        return collect($questions)->unique(fn ($q) => $q['prompt'].$q['answer'])->values()->all();
    }
}
