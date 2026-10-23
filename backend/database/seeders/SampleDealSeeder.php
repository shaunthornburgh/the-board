<?php

namespace Database\Seeders;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\DevelopmentType;
use App\Models\ScoutSeenListing;
use App\Models\Strategy;
use App\Services\DealPipeline;
use Illuminate\Database\Seeder;

/**
 * Twenty fictional permitted-development deals across the South East and East of England, plus four
 * sample scout candidates waiting in `new`. Streets, tenants and listings are invented; towns and
 * coordinates are approximate so the map works.
 */
class SampleDealSeeder extends Seeder
{
    public function run(): void
    {
        $typeId = DevelopmentType::where('slug', 'pd')->value('id');
        $strategies = Strategy::all();
        $pd = $strategies->where('slug', 'pd')->pluck('id');

        // [address, town, county, postcode, lat, lng, price, tenure, source, verdict, tenant, vacant sq ft, published, pack, origin]
        $rows = [
            ['14 Sample Row', 'Maidstone', 'Kent', 'ME14', 51.2752, 0.5229, 685000, 'freehold', 'Example Commercial', 'send', 'Vacant', 6200, true, true, 'bot'],
            ['Unit 3, Demo Court', 'Canterbury', 'Kent', 'CT1', 51.2794, 1.0802, 925000, 'freehold', 'Example Commercial', 'mention', 'Ground floor let to a sample accountancy practice, 2 years unexpired', 3400, true, false, 'bot'],
            ['Placeholder House, 2 Specimen Road', 'Guildford', 'Surrey', 'GU1', 51.2371, -0.5718, 1850000, 'freehold', 'Sample Property Search', 'send', 'Vacant', 11800, true, true, 'bot'],
            ['First and second floors, 9 Model Street', 'Woking', 'Surrey', 'GU21', 51.3195, -0.5567, 720000, 'leasehold', 'Sample Property Search', 'mention', 'Vacant', 4100, false, false, 'bot'],
            ['Illustration Buildings, 41 Mock Lane', 'Brighton', 'East Sussex', 'BN1', 50.8231, -0.1386, 1390000, 'freehold', null, 'send', 'Vacant', 7600, true, true, 'bot'],
            ['22 Exemplar Terrace', 'Eastbourne', 'East Sussex', 'BN21', 50.7692, 0.2871, 540000, 'freehold', 'Example Commercial', 'mention', 'Vacant', 3900, true, false, 'bot'],
            ['Trial House, Fictional Way', 'Crawley', 'West Sussex', 'RH10', 51.1104, -0.1851, 1100000, 'freehold', 'Sample Property Search', 'send', 'Part let to a sample recruitment firm, rolling licence', 9300, true, false, 'bot'],
            ['Pretend Chambers, 6 Test Street', 'Southampton', 'Hampshire', 'SO14', 50.9089, -1.4021, 830000, 'freehold', 'Example Commercial', 'send', 'Vacant', 8000, false, false, 'bot'],
            ['Dummy House, 18 Instance Road', 'Basingstoke', 'Hampshire', 'RG21', 51.2659, -1.0889, 990000, 'freehold', 'Sample Property Search', 'send', 'Vacant', 10400, true, true, 'bot'],
            ['Mockup Yard, 3 Sample Walk', 'Reading', 'Berkshire', 'RG1', 51.4551, -0.9762, 1625000, 'leasehold', 'Example Commercial', 'mention', 'Vacant, short licence to a sample charity ended last quarter', 8700, true, false, 'bot'],
            ['Proof House, 57 Example Lane', 'Oxford', 'Oxfordshire', 'OX4', 51.7409, -1.2187, 1475000, 'freehold', null, 'send', 'Vacant', 5800, true, true, 'bot'],
            ['Specimen Works, Placeholder Road', 'High Wycombe', 'Buckinghamshire', 'HP11', 51.6279, -0.7466, 760000, 'freehold', 'Sample Property Search', 'mention', 'Vacant', 6900, false, false, 'manual'],
            ['Template House, 11 Pattern Street', 'Watford', 'Hertfordshire', 'WD17', 51.6572, -0.3921, 1290000, 'freehold', 'Example Commercial', 'send', 'Vacant', 7200, true, false, 'bot'],
            ['Prototype Offices, 5 Mock Hill', 'St Albans', 'Hertfordshire', 'AL1', 51.7513, -0.3377, 1150000, 'freehold', 'Example Commercial', 'mention', 'Upper floor let to a sample design studio, 9 months left', 4300, true, false, 'bot'],
            ['Sample Point, 30 Demo Road', 'Chelmsford', 'Essex', 'CM1', 51.7349, 0.4701, 895000, 'freehold', 'Sample Property Search', 'send', 'Vacant', 7900, false, false, 'bot'],
            ['Exhibit House, 8 Trial Street', 'Colchester', 'Essex', 'CO1', 51.8966, 0.8937, 615000, 'freehold', 'Example Commercial', 'send', 'Vacant', 5100, true, true, 'bot'],
            ['Model Chambers, 12 Placeholder Quay', 'Ipswich', 'Suffolk', 'IP1', 52.0559, 1.1503, 470000, 'freehold', null, 'mention', 'Vacant', 4600, true, false, 'bot'],
            ['Instance House, 26 Sample Gate', 'Norwich', 'Norfolk', 'NR1', 52.6301, 1.2991, 655000, 'freehold', 'Sample Property Search', 'send', 'Vacant', 6500, false, false, 'bot'],
            ['Example Works, 4 Dummy Road', 'Peterborough', 'Cambridgeshire', 'PE1', 52.5703, -0.2421, 580000, 'freehold', 'Example Commercial', 'send', 'Vacant', 8400, true, false, 'bot'],
            ['Illustrative House, 15 Mock Street', 'Luton', 'Bedfordshire', 'LU1', 51.8794, -0.4183, 505000, 'leasehold', 'Sample Property Search', 'mention', 'Vacant', 5300, false, false, 'bot'],
        ];

        $publishedCount = 0;

        foreach ($rows as $i => [$address, $town, $county, $postcode, $lat, $lng, $price, $tenure, $source, $verdict, $tenant, $sqFt, $published, $pack, $origin]) {
            $n = $i + 1;
            $reference = sprintf('SAMPLE-%02d', $n);
            $flats = intdiv((int) round($sqFt * 0.78), 500);
            $appraisedAt = now()->subDays(40 - $n)->setTime(9, 0);

            $deal = Deal::updateOrCreate(['reference' => $reference], [
                'development_type_id' => $typeId,
                'status' => $published ? DealStatus::Published : DealStatus::AwaitingReview,
                'status_changed_at' => $published ? now()->subDays(30 - 2 * $publishedCount)->setTime(10, 0) : $appraisedAt,
                'origin' => $origin,
                'is_sample' => true,
                'address' => $address,
                'town' => $town,
                'county' => $county,
                'postcode' => $postcode,
                'latitude' => $lat,
                'longitude' => $lng,
                'photo_url' => '/images/deals/'.strtolower($reference).'.jpg',
                'asking_price' => $price,
                'tenure' => $tenure,
                'source_name' => $source,
                'source_url' => $source ? "https://example.com/listings/sample-{$n}" : null,
                'verdict' => $verdict,
                'bot_note' => $this->note($verdict, $town, $tenant, $sqFt, $flats, $tenure),
                'teaser' => $verdict === 'send'
                    ? "Office building in central {$town} that reads as a Class MA candidate."
                    : "{$town} office with one or two open points to check.",
                'tenant' => $tenant,
                'vacant_area_sq_ft' => $sqFt,
                'comps' => $n % 3 === 0 ? null : $this->comps($town, $county),
                'evidence' => $this->evidence($n, $sqFt),
                'appraised_at' => $appraisedAt,
                'pack_generated_at' => $pack ? $appraisedAt->copy()->addHours(2) : null,
                'published_at' => $published ? now()->subDays(30 - 2 * $publishedCount++)->setTime(10, 0) : null,
            ]);

            $deal->strategies()->sync($pd);
        }

        $this->candidates($typeId, $strategies);
    }

    /** Fresh scout pushes that have not been looked at yet: no photo, no appraisal. */
    private function candidates(int $typeId, $strategies): void
    {
        // [address, town, county, postcode, price, tenure, strategies, notes]
        $rows = [
            ['Former bank, 7 Sample Parade', 'Tonbridge', 'Kent', 'TN9', 640000, 'freehold', ['pd', 'potential-hmo'], 'Upper floors vacant per listing. Ground floor retail.'],
            ['31 Demo Avenue', 'Slough', 'Berkshire', 'SL1', 455000, 'freehold', ['hmo'], 'Seven-bed house, listing mentions existing HMO licence.'],
            ['Placeholder Court, 2 Mock Road', 'Harlow', 'Essex', 'CM20', 1200000, 'freehold', ['pd', 'social-housing'], 'Office block, agent mentions RP interest.'],
            ['12 Fictional Close', 'Bedford', 'Bedfordshire', 'MK40', 395000, 'leasehold', ['potential-hmo'], 'Large semi near the station.'],
        ];

        foreach ($rows as $i => [$address, $town, $county, $postcode, $price, $tenure, $tags, $notes]) {
            $n = 21 + $i;
            $key = sprintf('sample-%d', $n);

            $deal = Deal::updateOrCreate(['reference' => sprintf('SAMPLE-%02d', $n)], [
                'development_type_id' => $typeId,
                'status' => DealStatus::New,
                'status_changed_at' => now()->subHours(6 - $i),
                'origin' => 'scout',
                'scout' => 'sample-scout',
                'source' => 'example',
                'source_key' => $key,
                'is_sample' => true,
                'address' => $address,
                'town' => $town,
                'county' => $county,
                'postcode' => $postcode,
                'asking_price' => $price,
                'tenure' => $tenure,
                'source_name' => 'example.com',
                'source_url' => "https://example.com/listings/{$key}",
                'scout_notes' => "Sample. {$notes}",
            ]);

            $deal->strategies()->sync($strategies->whereIn('slug', $tags)->pluck('id'));
            if ($deal->wasRecentlyCreated) {
                app(DealPipeline::class)->created($deal, actor: 'sample-scout', note: 'Pushed by scout');
            }
            ScoutSeenListing::mark('example', $key, $deal->source_url, 'sample-scout', $deal->id);
        }
    }

    private function note(string $verdict, string $town, string $tenant, int $sqFt, int $flats, string $tenure): string
    {
        $occupancy = str_starts_with($tenant, 'Vacant') ? 'Listing says vacant' : 'Partly let, so vacancy needs checking';

        $lead = $verdict === 'send'
            ? "Sample. Office building in central {$town} that reads as a Class MA candidate."
            : "Sample. {$town} office with one or two open points.";

        return "{$lead} {$occupancy}. About ".number_format($sqFt)." sq ft by the listing, which the bot puts at roughly {$flats} flats before a measured survey. "
            .ucfirst($tenure).' per the listing.';
    }

    /** Illustrative one-bed flat prices by county, for sample comps only. */
    private const ONE_BED = [
        'Kent' => 210000, 'Surrey' => 285000, 'East Sussex' => 235000, 'West Sussex' => 215000,
        'Hampshire' => 190000, 'Berkshire' => 245000, 'Oxfordshire' => 290000, 'Buckinghamshire' => 225000,
        'Hertfordshire' => 275000, 'Essex' => 205000, 'Suffolk' => 165000, 'Norfolk' => 160000,
        'Cambridgeshire' => 150000, 'Bedfordshire' => 170000,
    ];

    private function comps(string $town, string $county): array
    {
        $unit = self::ONE_BED[$county];

        return [
            ['address' => "Sample flat, {$town} centre", 'price' => $unit, 'date' => now()->subMonths(4)->format('M Y'), 'note' => '1 bed, converted office'],
            ['address' => "Sample flat, {$town} station quarter", 'price' => (int) round($unit * 1.3 / 5000) * 5000, 'date' => now()->subMonths(7)->format('M Y'), 'note' => '2 bed, new build'],
            ['address' => "Sample flat, {$town} high street", 'price' => $unit - 15000, 'date' => now()->subMonths(9)->format('M Y'), 'note' => '1 bed, above retail'],
        ];
    }

    private function evidence(int $n, int $sqFt): array
    {
        return [
            'Current use' => 'Class E(g)(i) office, per listing',
            'Floorspace vs 1,500 m² limit' => 'About '.number_format($sqFt * 0.0929).' m², under the limit',
            'Article 4 direction' => $n % 4 === 0 ? 'One nearby; boundary to confirm' : 'None found for the site',
            'Conservation area' => $n % 5 === 0 ? 'Yes, edge of area' : 'No',
            'Listed building' => 'No',
            'Flood zone' => $n % 6 === 0 ? 'Zone 2' : 'Zone 1',
            'Prior approval history' => $n % 7 === 0 ? 'Lapsed approval from 2021' : 'None found',
        ];
    }
}
