<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Prabayar: tagihan bulan berjalan dibuat otomatis tiap tanggal 1 (jam lokal, lihat config/app.php timezone)
        $schedule->command('tagihan:generate')->monthlyOn(1, '00:10')->withoutOverlapping();

        // Notifikasi Telegram pelanggan PPPoE offline (lihat docs/superpowers/specs/2026-11-27-notif-telegram-offline-design.md)
        // Kunci tumpang tindih singkat: proses yang mati di tengah jalan tidak memblokir notifikasi 24 jam
        $schedule->command('ppp:notifikasi')->everyMinute()->withoutOverlapping(5);
        $schedule->command('ppp:cocokkan')->everyTenMinutes()->withoutOverlapping(15);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
