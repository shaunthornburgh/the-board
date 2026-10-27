<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The auction houses and portals the scouts work through, in priority order.
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->unsignedInteger('priority');
            $table->string('category');
            $table->string('name');
            $table->string('url', 250)->unique();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seeded here, not in a seeder, so existing volumes get them too (as with strategies).
        $now = now();
        $rows = [];

        foreach (['auction' => self::AUCTION, 'portal' => self::PORTAL] as $type => $csv) {
            foreach (array_filter(explode("\n", trim($csv))) as $line) {
                [$priority, $category, $name, $url, $notes] = str_getcsv($line, escape: '');
                $rows[] = [
                    'type' => $type,
                    'priority' => (int) $priority,
                    'category' => $category,
                    'name' => $name,
                    'url' => $url,
                    'notes' => $notes !== '' ? $notes : null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('sources')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }

    // priority,category,name,url,notes
    private const AUCTION = <<<'CSV'
1,Aggregator,Property Auction Action,https://www.propertyauctionaction.co.uk/,Hit first — many houses in one place
1,Aggregator,AuctionRadar,https://auctionradar.co.uk/,Hit first
2,National auction,Allsop Residential,https://www.allsop.co.uk/auctions/residential-auctions/,Current + next catalogue
2,National auction,Allsop Commercial,https://www.allsop.co.uk/auctions/commercial-auctions/,Current + next catalogue
2,National auction,Savills Auctions,https://auctions.savills.co.uk/,Resi and commercial lots
2,National auction,SDL Property Auctions,https://www.sdlauctions.co.uk/,Events + timed auctions
2,National auction,Acuitus,https://www.acuitus.co.uk/,Commercial / investment / portfolios
2,National auction,BidX1 UK,https://bidx1.com/en/united-kingdom,Online timed
2,National auction,Pugh Auctions,https://www.pugh-auctions.com/,Strong on insolvency lots
2,National auction,Clive Emson,https://www.cliveemson.co.uk/,South / East including Norfolk
2,National auction,Auction House UK,https://www.auctionhouse.co.uk/,Regional network
2,National auction,Knight Frank Auctions,https://www.knightfrank.co.uk/properties/auction,Check live catalogue
2,National auction,Strettons Auctions,https://www.strettons.co.uk/auctions,London / South East bias — still scan for non-London
2,National auction,Network Auctions,https://www.networkauctions.co.uk/,London / SE bias — still scan for non-London
3,Regional auction,Barnard Marcus Auctions,https://www.barnardmarcusauctions.co.uk/,When catalogue live
3,Regional auction,Barnett Ross,https://www.barnettross.co.uk/,When catalogue live
3,Regional auction,Harman Healy,https://www.harman-healy.co.uk/,When catalogue live
3,Regional auction,McHugh and Co,https://www.mchughandco.com/,When catalogue live
CSV;

    private const PORTAL = <<<'CSV'
1,Commercial portal,NovaLoca,https://www.novaloca.com/,Agents who skip expensive portals
1,Commercial portal,LoopNet UK,https://www.loopnet.co.uk/,Commercial for sale / investment
1,Commercial portal,Proplist,https://www.proplist.com/,Lower-cost commercial portal
1,Commercial portal,CommercialUK,https://www.commercial-uk.co.uk/,Aggregator of agent offices
2,National portal,Rightmove Commercial,https://www.rightmove.co.uk/commercial-property-for-sale.html,Commercial for sale
2,National portal,Zoopla Commercial,https://www.zoopla.co.uk/for-sale/commercial/,Commercial for sale
2,National portal,OnTheMarket Commercial,https://www.onthemarket.com/for-sale/commercial/,Commercial for sale
CSV;
};
