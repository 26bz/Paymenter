<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckForUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-for-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('app.version') == 'development') {
            $this->info('You are using the development version. No update check available.');

            return;
        }

        if (config('app.version') == 'beta') {
            $version = Http::get('https://api.paymenter.org/version?beta')->json();
            Setting::updateOrCreate(
                ['key' => 'latest_commit'],
                ['value' => $version['beta']]
            );
            if (config('app.commit') != $version['beta']) {
                $this->info('A new version is available: ' . $version['beta']);
                // Save as a variable to use in the UI
                $this->info('Latest version saved to database.');
            } else {
                $this->info('You are using the latest version: ' . config('app.commit'));
            }
        } else {
            $version = Http::get('https://api.paymenter.org/version')->json();

            if (config('app.version') != $version['latest']) {
                $this->info('A new version is available: ' . $version['latest']);
                // Save as a variable to use in the UI
                $this->info('Latest version saved to database.');
            } else {
                $this->info('You are using the latest version: ' . config('app.version'));
            }
        }
        // Check if we have a new latest stable version
        $setting = Setting::where('key', 'latest_version')->first();
        if ($setting && $setting->value != $version['latest']) {
            // Send notification to all admins
            $this->info('New stable version detected, sending notification to system email address.');
            $currentVersion = config('app.version');

            // Send notification to all admins
            NotificationHelper::sendSystemEmailNotification(
                'New stable version available',
                <<<HTML
                A new stable version of Paymenter is available: {$version['latest']}.<br>
                You are currently using version: {$currentVersion}.<br>
                
                Please update as soon as possible.
                HTML
            );
        }
        $setting->update(['value' => $version['latest']]);

        $this->info('Update check completed.');
    }
}
