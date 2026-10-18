<?php

namespace App\Providers;

use App\Models\PersonalityReport;
use App\Observers\PersonalityReportObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PersonalityReport::observe(PersonalityReportObserver::class);

        // Kunci terpisah dari throttle login/register (yang berbagi kunci IP),
        // supaya user yang barusan gagal login tetap bisa minta reset.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(6)->by('pwreset-ip|'.$request->ip()),
            Limit::perHour(5)->by('pwreset-email|'.strtolower((string) $request->input('email'))),
        ]);

        // Kunci terpisah per jenis aksi (throttle:N,M bernama-angka berbagi kunci dengan grup auth).
        RateLimiter::for('data-export', fn (Request $request) => Limit::perHour(3)->by('export|'.$request->user()?->id));
        RateLimiter::for('account-delete', fn (Request $request) => Limit::perHour(10)->by('delete|'.$request->user()?->id));
        RateLimiter::for('cv-upload', fn (Request $request) => Limit::perMinute(20)->by('cvup|'.$request->user()?->id));

        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->email));

        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Atur Ulang Kata Sandi Guratan')
            ->greeting('Halo '.$user->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Guratan Anda.')
            ->action('Atur Ulang Kata Sandi', ResetPassword::$createUrlCallback
                ? call_user_func(ResetPassword::$createUrlCallback, $user, $token)
                : url('/'))
            ->line('Tautan ini berlaku '.config('auth.passwords.users.expire').' menit. Abaikan email ini jika Anda tidak memintanya.')
            ->salutation('Salam, '.config('app.name')));
    }
}
