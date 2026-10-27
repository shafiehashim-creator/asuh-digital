<?php

namespace App\Http\Requests;

use App\Enums\AgeGroup;
use App\Enums\ChildAvatar;
use App\Services\GamePlaying;
use App\Services\VideoWatching;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:30'],
            // A child is in one group; "both" is for books only.
            'age_group' => ['required', Rule::in([AgeGroup::SixToNine->value, AgeGroup::TenToThirteen->value])],
            'avatar' => ['required', Rule::enum(ChildAvatar::class)],
            // Phase 2 parent controls for videos.
            'daily_video_minutes' => ['nullable', Rule::in(VideoWatching::LIMIT_CHOICES)],
            'read_before_watch' => ['sometimes', 'boolean'],
            'daily_game_minutes' => ['nullable', Rule::in(GamePlaying::LIMIT_CHOICES)],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null && $this->has('video_settings')) {
            $data['daily_video_minutes'] = $data['daily_video_minutes'] ?? null;
            $data['read_before_watch'] = $this->boolean('read_before_watch');
        }

        if ($key === null && $this->has('game_settings')) {
            $data['daily_game_minutes'] = $data['daily_game_minutes'] ?? null;
        }

        return $data;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nickname' => 'nama panggilan',
            'age_group' => 'kumpulan umur',
            'avatar' => 'avatar',
            'daily_video_minutes' => 'had masa menonton',
            'daily_game_minutes' => 'had masa bermain',
        ];
    }
}
