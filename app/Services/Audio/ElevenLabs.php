<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ElevenLabs for the audiobook: an AI voice for a page of text, with the time
 * each word is spoken, and the word times of a voice recorded elsewhere
 * (forced alignment). The API key lives only in .env (ELEVENLABS_API_KEY).
 */
class ElevenLabs
{
    public static function configured(): bool
    {
        return filled(config('services.elevenlabs.key'));
    }

    /**
     * @return array{audio: string, words: list<array{t: string, s: float, e: float}>}
     */
    public function speak(string $text, ?string $voiceId = null): array
    {
        $voiceId ??= (string) config('services.elevenlabs.voice_id');

        $response = $this->client()
            ->timeout(120)
            ->post("/v1/text-to-speech/{$voiceId}/with-timestamps?output_format=mp3_44100_64", [
                'text' => $text,
                'model_id' => config('services.elevenlabs.model_id'),
            ])->throw();

        $audio = base64_decode((string) $response->json('audio_base64'), true);
        if (! $audio) {
            throw new RuntimeException('ElevenLabs returned no audio.');
        }

        $alignment = $response->json('alignment') ?? [];

        return ['audio' => $audio, 'words' => self::wordsFromCharacters(
            $alignment['characters'] ?? [],
            $alignment['character_start_times_seconds'] ?? [],
            $alignment['character_end_times_seconds'] ?? [],
        )];
    }

    /**
     * When each word of the text is spoken in a recording.
     *
     * @return list<array{t: string, s: float, e: float}>
     */
    public function align(string $audioPath, string $text): array
    {
        $response = $this->client()
            ->timeout(120)
            ->attach('file', fopen($audioPath, 'r'), 'page.mp3')
            ->post('/v1/forced-alignment', ['text' => $text])
            ->throw();

        return collect($response->json('words') ?? [])
            ->filter(fn ($word) => trim((string) ($word['text'] ?? '')) !== '')
            ->map(fn ($word) => ['t' => trim($word['text']), 's' => round((float) $word['start'], 3), 'e' => round((float) $word['end'], 3)])
            ->values()->all();
    }

    /** @return list<array{t: string, s: float, e: float}> characters grouped into words */
    public static function wordsFromCharacters(array $chars, array $starts, array $ends): array
    {
        $words = [];
        $current = null;
        foreach ($chars as $i => $char) {
            if (trim($char) === '') {
                if ($current) {
                    $words[] = $current;
                    $current = null;
                }

                continue;
            }
            if ($current === null) {
                $current = ['t' => '', 's' => round((float) ($starts[$i] ?? 0), 3), 'e' => 0.0];
            }
            $current['t'] .= $char;
            $current['e'] = round((float) ($ends[$i] ?? $current['s']), 3);
        }
        if ($current) {
            $words[] = $current;
        }

        return $words;
    }

    private function client()
    {
        if (! self::configured()) {
            throw new RuntimeException('ELEVENLABS_API_KEY is not set.');
        }

        return Http::baseUrl((string) config('services.elevenlabs.url'))
            ->withHeaders(['xi-api-key' => (string) config('services.elevenlabs.key')])
            ->acceptJson();
    }
}
