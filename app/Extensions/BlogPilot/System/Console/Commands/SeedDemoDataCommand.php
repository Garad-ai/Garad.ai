<?php

namespace App\Extensions\BlogPilot\System\Console\Commands;

use App\Extensions\BlogPilot\System\Database\Seeders\BlogPilotDemoSeeder;
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
    protected $signature = 'blogpilot:seed-demo-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo agents and posts for BlogPilot extension';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting BlogPilot demo data seeding...');
        $this->newLine();

        Log::info('blogpilot:seed-demo-data started');

        $seeder = new BlogPilotDemoSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('✅ Demo data seeding completed!');
        Log::info('blogpilot:seed-demo-data finished');

        return CommandAlias::SUCCESS;
    }
}
