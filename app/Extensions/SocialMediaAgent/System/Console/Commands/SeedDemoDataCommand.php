<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Database\Seeders\SocialMediaAgentDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social-media-agent:seed-demo-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo agents and posts for Social Media Agent extension';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Social Media Agent demo data seeding...');
        $this->newLine();

        Log::info('social-media-agent:seed-demo-data started');

        $seeder = new SocialMediaAgentDemoSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('✅ Demo data seeding completed!');
        Log::info('social-media-agent:seed-demo-data finished');

        return CommandAlias::SUCCESS;
    }
}
