<?php

namespace App\Services\Analyst;

use App\Enums\AnalystVerdict;
use App\Models\Deal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The Grok Analyst bot (ANALYST_DRIVER=grok). NOT YET CONNECTED: this is the intended contract,
 * untested against the real bot.
 *
 * Request:  POST {GROK_ANALYST_URL}
 *           Authorization: Bearer {GROK_API_KEY}
 *           { "model": GROK_MODEL, "deal": { id, reference, source, source_key, source_url, address, town,
 *             county, postcode, asking_price, tenure, type, strategies[], scout_notes } }
 * Response: 200 { "verdict": "SEND" | "MENTION" | "KILL" | "REJECT", "notes": "..." }
 *
 * KILL is stored as reject. Anything else (non-2xx, timeout, unknown verdict) throws
 * AnalystException; the job records the message on the deal and retries.
 */
class GrokAnalystClient implements AnalystClient
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $key,
        private readonly ?string $model,
        private readonly int $timeout,
    ) {}

    public function analyse(Deal $deal): AnalystResult
    {
        if (blank($this->url) || blank($this->key)) {
            throw new AnalystException('The Grok analyst is not configured. Set GROK_ANALYST_URL and GROK_API_KEY, or use ANALYST_DRIVER=stub.');
        }

        $deal->loadMissing('developmentType', 'strategies');

        try {
            $response = Http::withToken($this->key)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->url, [
                    'model' => $this->model,
                    'deal' => [
                        'id' => $deal->id,
                        'reference' => $deal->reference,
                        'source' => $deal->source,
                        'source_key' => $deal->source_key,
                        'source_url' => $deal->source_url,
                        'address' => $deal->address,
                        'town' => $deal->town,
                        'county' => $deal->county,
                        'postcode' => $deal->postcode,
                        'asking_price' => $deal->asking_price,
                        'tenure' => $deal->tenure,
                        'type' => $deal->developmentType?->slug,
                        'strategies' => $deal->strategies->pluck('label')->all(),
                        'scout_notes' => $deal->scout_notes,
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new AnalystException('Could not reach the Grok analyst: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new AnalystException("The Grok analyst returned HTTP {$response->status()}.");
        }

        $verdict = AnalystVerdict::fromBot($response->json('verdict'));
        if (! $verdict) {
            throw new AnalystException('The Grok analyst returned an unknown verdict: '.json_encode($response->json('verdict')));
        }

        return new AnalystResult($verdict, $response->json('notes'));
    }
}
