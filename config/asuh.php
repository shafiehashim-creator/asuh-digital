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
        // Bank transfer buyers need time to transfer and upload a receipt.
        'bank_transfer_reservation_hours' => 24,
        'pass_start_rule' => 'on_payment',
        'launch_date' => null,

        // Phase B catalogue and reader stay hidden from the public until the
        // admin opens them (deploys are automatic, so unfinished work can ship).
        'catalog_open' => false,
        // Phase 2 animated videos and Langganan Plus stay hidden until the admin opens them.
        'videos_open' => false,
        // Pages anyone may read free from a book's "versi pantas" (0 turns previews off).
        'preview_pages' => 5,
        // Floating WhatsApp button (digits with country code, e.g. 60123456789); hidden when empty.
        'whatsapp_number' => null,
        // CHIP processing fee passed on to buyers, per method: fixed RM plus percent.
        // Cards are never charged a fee (no surcharging card payments in Malaysia).
        'chip_fee_fpx_fixed' => 0,
        'chip_fee_fpx_percent' => 0,
        'chip_fee_ewallet_fixed' => 0,
        'chip_fee_ewallet_percent' => 0,
        // Affiliates: open for applications, default commission, refund window
        // before a commission is due, and how long a ?ref= link is remembered.
        'affiliates_open' => false,
        'affiliate_commission_percent' => 10,
        'affiliate_hold_days' => 14,
        'affiliate_cookie_days' => 30,
        // Royalty report defaults: share of recognised subscription revenue paid out,
        // the e-book share of Langganan Plus, the minutes that count as a read,
        // and the royalty on single e-book sales.
        'royalty_pool_percent' => 30,
        'royalty_plus_ebook_share' => 60,
        'royalty_min_minutes' => 5,
        'royalty_single_percent' => 10,
        // The ASUH music video (YouTube link) and the pages that show it.
        'brand_video_url' => null,
        'brand_video_title' => 'Lagu ASUH: Jom Jadi Baik!',
        'brand_video_places' => ['home', 'about', 'account', 'child_mode'],

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
                'question' => 'Berapa orang anak boleh guna satu langganan?',
                'answer' => 'Satu Langganan Tahunan untuk seisi keluarga. Anda boleh mencipta sehingga 4 profil anak, dan setiap anak melihat buku yang sesuai dengan kumpulan umurnya.',
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
                'answer' => 'Harga early bird hanya untuk keluarga yang terawal menyokong ASUH Digital. Selepas slot habis, Langganan Tahunan dijual pada harga biasa.',
            ],
        ],
        'refund_policy' => 'Bayaran balik penuh boleh dimohon sebelum platform ASUH Digital dilancarkan.',

        // English versions (bilingual site, 2026-10-26); empty falls back to the BM text above.
        'landing_headline_en' => 'A digital Islamic bookshelf for children aged 6–13',
        'landing_subheadline_en' => 'Comics, stories of the prophets, good character and daily prayers, all syariah-reviewed. Jom Jadi Baik!',
        'landing_faq_en' => [
            [
                'question' => 'What is ASUH Digital?',
                'answer' => 'A digital Islamic bookshelf from ASUH, a Galeri Ilmu Media Group brand, for children aged 6 to 13. Children can read comics, stories of the prophets, books on good character, daily prayers and sirah on a phone, tablet or computer.',
            ],
            [
                'question' => 'How many children can use one subscription?',
                'answer' => 'One Yearly Subscription covers the whole family. You can create up to 4 child profiles, and each child sees the books for their age group.',
            ],
            [
                'question' => 'Is the content syariah-reviewed?',
                'answer' => 'Yes. Every book must pass a syariah review before it is published on ASUH Digital.',
            ],
            [
                'question' => 'Is it safe for my child?',
                'answer' => 'Kids Mode shows no prices, buy buttons or account settings. To leave Kids Mode, a parent has to pass the parent gate. We only keep a nickname and age group for each child, with no photos or dates of birth.',
            ],
            [
                'question' => 'How do I pay?',
                'answer' => 'By FPX, credit or debit card or e-wallet (through CHIP), or by bank transfer. You pay once for 12 months. There are no automatic charges.',
            ],
            [
                'question' => 'Why are the slots limited?',
                'answer' => 'The early bird price is only for the first families to support ASUH Digital. Once the slots are gone, the Yearly Subscription is sold at the regular price.',
            ],
        ],
        'refund_policy_en' => 'A full refund can be requested before the ASUH Digital platform launches.',

        // Institution pilot
        'pilot_duration_days' => 30,
        'admin_notification_emails' => [],

        // Bank transfer
        'bank_name' => null,
        'bank_account_name' => null,
        'bank_account_number' => null,

        // Company details printed on receipts and the privacy/terms pages
        'contact_email' => null,
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
