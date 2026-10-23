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
        'landing_faq' => [],
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
