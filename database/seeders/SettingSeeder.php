<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = array_merge(Setting::FORMULA_DEFAULTS, Setting::STATE_DEFAULTS);

        foreach ($defaults as $key => $value) {
            // firstOrCreate: aman dijalankan ulang, tidak menimpa nilai yang sudah diubah Admin
            Setting::query()->firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
