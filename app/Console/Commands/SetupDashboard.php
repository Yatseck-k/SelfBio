<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupDashboard extends Command
{
    protected $signature = 'dashboard:setup';
    
    protected $description = 'Setup dashboard with demo data and configurations';

    public function handle()
    {
        $this->info('🚀 Setting up SelfBio Dashboard...');

        // Run migrations
        $this->info('📊 Running migrations...');
        Artisan::call('migrate', ['--force' => true]);
        
        // Seed demo data
        $this->info('🌱 Seeding demo data...');
        Artisan::call('db:seed', ['--class' => 'DashboardSeeder']);
        
        // Clear and cache config
        $this->info('⚡ Optimizing application...');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        
        // Create storage link
        $this->info('🔗 Creating storage link...');
        Artisan::call('storage:link');

        $this->info('✅ Dashboard setup completed!');
        $this->info('');
        $this->info('📝 Demo credentials:');
        $this->info('   Email: test@test.com');
        $this->info('   Password: password');
        $this->info('');
        $this->info('🌐 Access points:');
        $this->info('   Dashboard: /dashboard');
        $this->info('   Admin: /admin');
        $this->info('   API: /api/*');

        return 0;
    }
}