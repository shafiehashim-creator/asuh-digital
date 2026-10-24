<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Setting defaults
    |--------------------------------------------------------------------------
    |
    | Fallback values for App\Models\Setting. Anything saved from the admin
    | panel overrides these. User-facing text is in Bahasa Melayu.
    |
    */

    'settings' => [
        // Pre-order
        'preorder_plan_code' => 'pas_tahunan_early',
        'preorder_slot_limit' => 100,
        'preorder_reservation_minutes' => 30,
        'pass_start_rule' => 'on_payment',
        'launch_date' => null,

        // Landing page copy
        'landing_headline' => 'Rak buku Islamik digital untuk anak 6–13 tahun',
        'landing_subheadline' => 'Komik, kisah nabi, akhlak dan doa harian yang telah disemak syariah. Jom Jadi Baik!',
        // Answers that depend on other settings (when the pass starts, refunds)
        // are rendered by the landing page itself, so they never go stale.
        'landing_faq' => [
            [
                'question' => 'Apa itu ASUH Digital?',
                'answer' => 'Rak buku Islamik digital daripada ASUH, jenama Galeri Ilmu Media Group, untuk kanak-kanak berumur 6 hingga 13 tahun. Anak boleh membaca komik, kisah nabi, buku akhlak, doa harian dan sirah di telefon, tablet atau komputer.',
            ],
            [
                'question' => 'Berapa orang anak boleh guna satu Pas?',
                'answer' => 'Satu Pas Tahunan untuk seisi keluarga. Anda boleh mencipta sehingga 4 profil anak, dan setiap anak melihat buku yang sesuai dengan kumpulan umurnya.',
            ],
            [
                'question' => 'Adakah kandungannya disemak syariah?',
                'answer' => 'Ya. Setiap buku mesti lulus semakan syariah sebelum diterbitkan di ASUH Digital.',
            ],
            [
                'question' => 'Selamatkah untuk anak saya?',
                'answer' => 'Mod Anak tidak memaparkan harga, butang beli atau tetapan akaun. Untuk keluar dari Mod Anak, ibu bapa perlu melepasi pintu ibu bapa. Kami hanya menyimpan nama panggilan dan kumpulan umur anak, tanpa foto atau tarikh lahir.',
            ],
            [
                'question' => 'Bagaimana cara membayar?',
                'answer' => 'Melalui FPX, kad kredit/debit atau e-wallet (dengan CHIP), atau pindahan bank. Bayaran dibuat sekali sahaja untuk 12 bulan. Tiada caj automatik.',
            ],
            [
                'question' => 'Kenapa slot terhad?',
                'answer' => 'Harga early bird hanya untuk keluarga yang terawal menyokong ASUH Digital. Selepas slot habis, Pas Tahunan dijual pada harga biasa.',
            ],
        ],
        'refund_policy' => 'Bayaran balik penuh boleh dimohon sebelum platform ASUH Digital dilancarkan.',

        // Institution pilot
        'pilot_duration_days' => 30,
        'admin_notification_emails' => [],

        // Bank transfer
        'bank_name' => null,
        'bank_account_name' => null,
        'bank_account_number' => null,

        // Company details printed on receipts
        'company_name' => null,
        'company_ssm_no' => null,
        'company_address' => null,
        'company_sst_no' => null,
    ],

    'states' => [
        'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang',
        'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor',
        'Terengganu', 'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
        'Luar Malaysia',
    ],

];
