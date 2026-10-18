<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\AuditLog;
use App\Models\GrafologAvailability;
use App\Models\User;
use App\Services\ClientInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserLookupController extends Controller
{
    /**
     * Cari 1 klien (role=user) berdasarkan email PERSIS - dipakai grafolog
     * untuk memilih pemilik sample baru. Sengaja bukan endpoint listing/pencarian
     * bebas supaya tidak jadi direktori email semua pengguna (data sensitif).
     */
    public function byEmail(Request $request): JsonResponse
    {
        abort_unless($request->user()->isGrafolog(), 403);

        $request->validate(['email' => ['required', 'email']]);

        $client = User::where('email', $request->query('email'))
            ->where('role', 'user')
            ->first(['id', 'name', 'email']);

        abort_unless($client, 404, 'Klien tidak ditemukan.');

        return response()->json($client);
    }

    /**
     * Daftarkan klien baru langsung dari Portal Grafolog (2026-08-07) - untuk
     * klien walk-in yang belum pernah /auth/register sendiri. Grafolog TIDAK
     * bisa membuat akun untuk role lain (staf tetap lewat Admin\AdminUserController,
     * lihat catatannya) - endpoint ini hanya pernah membuat role 'user'.
     *
     * `password` boleh dikosongkan - kalau begitu, sistem generate password
     * acak dan mengembalikannya SEKALI di response (`generated_password`)
     * supaya grafolog bisa sampaikan langsung ke klien secara lisan/tertulis.
     * Ini BUKAN dikirim lewat email undangan seperti pola SaaS umumnya,
     * karena MAIL_MAILER proyek ini masih `log` (belum tersambung SMTP
     * sungguhan) - jalur email baru masuk akal setelah itu beres.
     */
    public function store(StoreClientRequest $request, ClientInvitationService $invitations): JsonResponse
    {
        $grafolog = $request->user();
        abort_unless($grafolog->isGrafolog(), 403);

        // Klien bawaan grafolog (2026-10-17): tidak ada lagi password dari grafolog. Klien
        // menerima undangan email dan mengatur kata sandinya sendiri, supaya akunnya benar-benar
        // dipakai (laporan, promo, produk lain). referred_by mencatat grafolog pembawanya.
        $client = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Str::password(32),
            'role' => 'user',
            'acquisition_source' => 'grafolog',
            'referred_by' => $grafolog->id,
            'consented_at' => now(),
            'consent_source' => 'grafolog',
            'consent_version' => User::CONSENT_VERSION,
        ]);

        $url = $invitations->send($client, $grafolog);
        AuditLog::record('daftarkan_klien', User::class, $client->id, $grafolog->id, $request->ip());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'acquisition_source' => 'grafolog',
            'invitation_sent' => true,
            // Hanya untuk uji lokal (MAIL_MAILER=log): tautan ini = akses akun klien.
            'invitation_url' => app()->environment('local', 'testing') ? $url : null,
        ], 201);
    }

    /** Kirim ulang undangan - hanya grafolog pembawa klien tersebut. */
    public function resendInvitation(Request $request, User $client, ClientInvitationService $invitations): JsonResponse
    {
        $grafolog = $request->user();
        abort_unless($grafolog->isGrafolog() && $client->referred_by === $grafolog->id, 403);

        $url = $invitations->send($client, $grafolog);
        AuditLog::record('kirim_ulang_undangan_klien', User::class, $client->id, $grafolog->id, $request->ip());

        return response()->json([
            'invitation_sent' => true,
            'invitation_url' => app()->environment('local', 'testing') ? $url : null,
        ]);
    }

    /**
     * Daftar semua grafolog - dipakai HR/Administrator untuk memilih siapa
     * yang di-assign ke sebuah sample (AssignmentController). Gated lewat
     * middleware 'role:hr,administrator' di routes/api.php, bukan inline
     * abort_unless seperti byEmail() di atas - pola baru sejak MGA Fase 05.
     */
    /**
     * Grafolog terverifikasi + profil, beban & kalender setahun - dasar HR memilih grafolog.
     * `?month=YYYY-MM` (default bulan ini, dalam jendela 12 bulan): HR hanya melihat yang bisa
     * dipesan bulan itu; `calendar` = bulan-bulan yang DIATUR grafolog (catatan ketersediaan setahun).
     * Admin melihat semua beserta statusnya.
     */
    public function grafologs(Request $request): JsonResponse
    {
        $window = GrafologAvailability::window();
        $month = $request->validate(['month' => ['sometimes', Rule::in($window)]])['month'] ?? $window[0];
        $isHr = $request->user()->isHr();

        $list = User::where('role', 'grafolog')
            ->where(fn ($q) => $q->whereNull('grafolog_status')->orWhere('grafolog_status', 'verified'))
            ->withCount([
                'assignments as active_assignments' => fn ($q) => $q->where('status', 'assigned'),
                'assignments as completed_assignments' => fn ($q) => $q->where('status', 'completed'),
                'serviceDeliveries as active_services' => fn ($q) => $q->whereIn('status', ['ditugaskan', 'dijadwalkan']),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'city', 'bio', 'specializations', 'experience_years', 'grafolog_verified_at']);

        $rows = GrafologAvailability::whereIn('user_id', $list->pluck('id'))->whereIn('month', $window)
            ->get(['user_id', 'month', 'status'])->groupBy('user_id');

        $list = $list->map(fn (User $g) => $g->toArray() + [
            'availability' => GrafologAvailability::forMonth($g, $month),
            'calendar' => ($rows[$g->id] ?? collect())->sortBy('month')
                ->map(fn ($r) => ['month' => $r->month, 'status' => $r->status])->values(),
        ]);

        return response()->json($isHr ? $list->filter(fn ($g) => $g['availability']['bookable'])->values() : $list->values());
    }
}
