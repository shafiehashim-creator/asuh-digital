<?php

/*
| Bahasa Melayu validation messages. Rules not listed here fall back to
| lang/en/validation.php (app.fallback_locale).
*/

return [

    'accepted' => ':Attribute perlu dipersetujui.',
    'array' => ':Attribute mesti dalam bentuk senarai.',
    'between' => [
        'numeric' => ':Attribute mesti antara :min dan :max.',
        'string' => ':Attribute mesti antara :min dan :max aksara.',
        'file' => ':Attribute mesti antara :min dan :max kilobait.',
    ],
    'boolean' => ':Attribute mesti ya atau tidak.',
    'confirmed' => 'Pengesahan :attribute tidak sepadan.',
    'current_password' => 'Kata laluan semasa tidak betul.',
    'date' => ':Attribute bukan tarikh yang sah.',
    'digits_between' => ':Attribute mesti antara :min dan :max digit.',
    'email' => ':Attribute mesti alamat e-mel yang sah.',
    'enum' => ':Attribute yang dipilih tidak sah.',
    'file' => ':Attribute mesti fail.',
    'image' => ':Attribute mesti gambar.',
    'in' => ':Attribute yang dipilih tidak sah.',
    'integer' => ':Attribute mesti nombor bulat.',
    'max' => [
        'numeric' => ':Attribute tidak boleh melebihi :max.',
        'string' => ':Attribute tidak boleh melebihi :max aksara.',
        'file' => ':Attribute tidak boleh melebihi :max kilobait.',
        'array' => ':Attribute tidak boleh melebihi :max item.',
    ],
    'mimes' => ':Attribute mesti fail jenis: :values.',
    'min' => [
        'numeric' => ':Attribute mesti sekurang-kurangnya :min.',
        'string' => ':Attribute mesti sekurang-kurangnya :min aksara.',
        'file' => ':Attribute mesti sekurang-kurangnya :min kilobait.',
        'array' => ':Attribute mesti ada sekurang-kurangnya :min item.',
    ],
    'numeric' => ':Attribute mesti nombor.',
    'password' => [
        'letters' => ':Attribute mesti mengandungi sekurang-kurangnya satu huruf.',
        'mixed' => ':Attribute mesti mengandungi huruf besar dan huruf kecil.',
        'numbers' => ':Attribute mesti mengandungi sekurang-kurangnya satu nombor.',
        'symbols' => ':Attribute mesti mengandungi sekurang-kurangnya satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor dalam insiden data. Sila pilih kata laluan lain.',
    ],
    'regex' => 'Format :attribute tidak sah.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute mesti teks.',
    'unique' => ':Attribute ini sudah digunakan.',
    'url' => ':Attribute mesti pautan yang sah.',

    'custom' => [
        'email' => [
            'unique' => 'E-mel ini sudah digunakan oleh akaun lain. Jika ia milik anda, sila log masuk.',
        ],
        'phone' => [
            'regex' => 'Sila masukkan no. telefon bimbit Malaysia yang sah, contohnya 012-345 6789.',
        ],
        'pdpa_consent' => [
            'accepted' => 'Anda perlu bersetuju dengan notis privasi untuk meneruskan.',
        ],
    ],

    'attributes' => [
        'name' => 'nama',
        'email' => 'e-mel',
        'phone' => 'no. telefon',
        'password' => 'kata laluan',
        'current_password' => 'kata laluan semasa',
        'password_confirmation' => 'pengesahan kata laluan',
        'pdpa_consent' => 'notis privasi',
        'state' => 'negeri',
        'district' => 'daerah',
        'children_count' => 'bilangan anak',
        'children_age_group' => 'kumpulan umur anak',
        'institution_name' => 'nama institusi',
        'institution_type' => 'jenis institusi',
        'contact_name' => 'nama pegawai',
        'contact_position' => 'jawatan',
        'student_count' => 'anggaran bilangan murid',
        'age_group' => 'kumpulan umur',
        'notes' => 'catatan',
        'receipt' => 'resit',
        'promo_code' => 'kod promosi',
        'payment_method' => 'kaedah bayaran',
    ],

];
