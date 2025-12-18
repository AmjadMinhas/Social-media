<?php

namespace App\Console\Commands;

use App\Services\UnifiedSocialInboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSocialMediaMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:sync-messages {--organization= : Organization ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync messages from all connected social media platforms';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting social media message sync...');

        $organizationId = $this->option('organization');
        $inboxService = new UnifiedSocialInboxService();

        if ($organizationId) {
            // Sync specific organization
            $this->info("Syncing organization ID: {$organizationId}");
            $result = $inboxService->syncAllPlatforms($organizationId);
            
            if ($result['success']) {
                $this->info("✓ Synced {$result['results']['total']} messages");
                $this->info("  - Facebook: {$result['results']['facebook']}");
                $this->info("  - Instagram: {$result['results']['instagram']}");
                $this->info("  - Twitter: {$result['results']['twitter']}");
            } else {
                $this->error("✗ Sync failed: {$result['message']}");
            }
        } else {
            // Sync all organizations
            $organizations = \App\Models\Organization::all();
            
            $this->info("Syncing {$organizations->count()} organizations...");
            
            $totalSynced = 0;
            foreach ($organizations as $organization) {
                $this->info("Syncing organization: {$organization->id}");
                
                $result = $inboxService->syncAllPlatforms($organization->id);
                
                if ($result['success']) {
                    $synced = $result['results']['total'] ?? 0;
                    $totalSynced += $synced;
                    $this->info("  ✓ Synced {$synced} messages");
                } else {
                    $this->warn("  ✗ Failed: {$result['message']}");
                }
            }
            
            $this->info("Total messages synced: {$totalSynced}");
        }

        $this->info('Sync completed!');
        return 0;
    }
}










