<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\UsageEvent;
use Illuminate\Database\Seeder;

class UsageEventDemoSeeder extends Seeder
{
    /**
     * Seed sample usage events for local dashboard testing.
     */
    public function run(): void
    {
        $organizations = Organization::query()->get(['id']);

        foreach ($organizations as $organization) {
            $eventsToCreate = random_int(8, 20);

            for ($index = 0; $index < $eventsToCreate; $index++) {
                UsageEvent::query()->create([
                    'organization_id' => $organization->id,
                    'event_type' => 'api_call',
                    'quantity' => random_int(1, 5),
                    'metadata' => [
                        'source' => 'seeder.usage-event-demo',
                    ],
                    'recorded_at' => now()->startOfMonth()->addDays(random_int(0, 27)),
                ]);
            }
        }
    }
}
