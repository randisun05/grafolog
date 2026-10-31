<?php

namespace App\Services\Commerce;

use App\Models\Assignment;
use App\Models\GrafologAvailability;
use App\Models\GrafologRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Penentuan grafolog untuk pesanan klien individu (2026-10-30):
 * bayar -> grafolog yang dipilih klien punya 24 jam untuk menerima/menolak -> bila menolak/lewat waktu klien
 * memilih lagi (atau meminta rekomendasi admin); setelah MAX_ATTEMPTS gagal otomatis masuk antrean admin.
 * Penugasan manual admin tetap berlaku kapan saja dan mengakhiri alur ini.
 */
class GrafologMatchingService
{
    public const CONFIRM_HOURS = 24;

    public const MAX_ATTEMPTS = 3;

    /** Pengingat dikirim ke grafolog saat sisa waktu konfirmasi tinggal sekian jam. */
    public const REMIND_WHEN_HOURS_LEFT = 12;

    public function __construct(private Notifier $notifier) {}

    public static function monthOf(Order $order): string
    {
        return ($order->scheduled_for ?? now())->format('Y-m');
    }

    /** Grafolog boleh dipilih untuk bulan tsb (terverifikasi, tersedia, belum pernah menolak pesanan ini). */
    public function assertSelectable(User $grafolog, string $month, ?Order $order = null): void
    {
        abort_unless($grafolog->isGrafolog(), 422, 'Grafolog tidak valid.');
        abort_unless(GrafologAvailability::forMonth($grafolog, $month)['bookable'], 422, 'Grafolog tidak tersedia pada bulan pelaksanaan yang dipilih.');
        if ($order) {
            abort_if($order->grafologRequests()->where('grafolog_id', $grafolog->id)
                ->whereIn('status', [GrafologRequest::DECLINED, GrafologRequest::EXPIRED])->exists(), 422, 'Grafolog ini sudah tidak menerima pesanan ini. Pilih grafolog lain.');
        }
    }

    /** Dipanggil saat pesanan klien individu lunas. */
    public function startOnPaid(Order $order): void
    {
        $grafolog = $order->grafolog_choice === 'pilih' && $order->grafolog_id ? User::find($order->grafolog_id) : null;
        $grafolog ? $this->request($order, $grafolog) : $this->toAdmin($order);
    }

    public function request(Order $order, User $grafolog): GrafologRequest
    {
        $req = DB::transaction(function () use ($order, $grafolog) {
            $order->grafologRequests()->where('status', GrafologRequest::WAITING)->update(['status' => GrafologRequest::CANCELLED]);
            $req = $order->grafologRequests()->create([
                'grafolog_id' => $grafolog->id, 'status' => GrafologRequest::WAITING, 'expires_at' => now()->addHours(self::CONFIRM_HOURS),
            ]);
            $order->update(['grafolog_id' => $grafolog->id, 'grafolog_choice' => 'pilih', 'grafolog_state' => 'menunggu_konfirmasi', 'grafolog_deadline' => $req->expires_at]);

            return $req;
        });
        $this->notifier->send($grafolog, 'permintaan_grafolog', 'Permintaan penanganan baru',
            "Pesanan #{$order->id} ({$order->product->nama}) memilih Anda. Mohon konfirmasi dalam ".self::CONFIRM_HOURS.' jam.', '/grafolog/ditugaskan');
        $this->notifier->send($order->user, 'status_pesanan', 'Menunggu konfirmasi grafolog',
            "Permintaan untuk pesanan #{$order->id} dikirim ke {$grafolog->name}. Ia punya ".self::CONFIRM_HOURS.' jam untuk menjawab.', '/riwayat');

        return $req;
    }

    public function toAdmin(Order $order): void
    {
        $order->update(['grafolog_id' => null, 'grafolog_choice' => 'rekomendasi', 'grafolog_state' => 'menunggu_admin', 'grafolog_deadline' => null]);
        $this->notifier->toAdmins('perlu_penugasan', 'Pesanan klien perlu rekomendasi grafolog',
            "Pesanan #{$order->id} ({$order->product->nama}) membutuhkan penugasan grafolog.", '/admin/orders');
        $this->notifier->send($order->user, 'status_pesanan', 'Tim kami mencarikan grafolog',
            "Pesanan #{$order->id} sedang dicarikan grafolog yang cocok oleh tim kami. Anda akan diberi tahu begitu ada yang menerima.", '/riwayat');
    }

    /** Jawaban grafolog. */
    public function respond(GrafologRequest $req, User $grafolog, bool $accept, ?string $note = null): void
    {
        abort_unless($req->grafolog_id === $grafolog->id, 403);
        abort_unless($req->status === GrafologRequest::WAITING, 422, 'Permintaan ini sudah dijawab atau tidak berlaku.');
        if ($req->expires_at->isPast()) {
            $this->release($req, GrafologRequest::EXPIRED, 'Lewat batas waktu konfirmasi.');
            abort(422, 'Batas waktu konfirmasi sudah lewat.');
        }

        if (! $accept) {
            $this->release($req, GrafologRequest::DECLINED, $note);

            return;
        }
        DB::transaction(function () use ($req, $grafolog) {
            $req->update(['status' => GrafologRequest::ACCEPTED, 'responded_at' => now()]);
            $this->finalize($req->order, $grafolog, $req->order->user_id);
        });
        $order = $req->order->fresh(['user', 'product']);
        $this->notifier->send($order->user, 'status_pesanan', 'Grafolog menerima pesanan Anda',
            "{$grafolog->name} menerima pesanan #{$order->id}. Pengerjaan akan dimulai setelah persyaratan Anda lengkap.", '/riwayat');
        $this->notifier->send($grafolog, 'penugasan', 'Penugasan dikonfirmasi',
            "Pesanan #{$order->id} ({$order->product->nama}) sekarang menjadi tanggung jawab Anda.", '/grafolog/ditugaskan');
    }

    /** Klien memilih ulang (atau meminta rekomendasi admin) setelah penolakan; atau mengubah pilihan sebelum bayar. */
    public function clientChoose(Order $order, ?User $grafolog, bool $recommend): void
    {
        $paid = $order->isPaid();
        abort_if($paid && $order->grafolog_state !== 'menunggu_pilih', 422, 'Pesanan ini tidak sedang menunggu pilihan grafolog.');
        abort_if(! $paid && $order->status !== Order::STATUS_UNPAID, 422, 'Pesanan tidak dapat diubah.');

        if ($recommend) {
            $paid ? $this->toAdmin($order) : $order->update(['grafolog_id' => null, 'grafolog_choice' => 'rekomendasi']);

            return;
        }
        abort_unless($grafolog, 422, 'Pilih grafolog.');
        $this->assertSelectable($grafolog, self::monthOf($order), $order);
        $paid ? $this->request($order, $grafolog) : $order->update(['grafolog_id' => $grafolog->id, 'grafolog_choice' => 'pilih']);
    }

    /** Penugasan administrator: langsung final, tanpa menunggu konfirmasi. */
    public function adminAssign(Order $order, User $grafolog, User $admin): void
    {
        abort_unless($grafolog->isGrafolog(), 422, 'User yang dipilih bukan grafolog.');
        DB::transaction(function () use ($order, $grafolog, $admin) {
            $order->grafologRequests()->where('status', GrafologRequest::WAITING)->update(['status' => GrafologRequest::CANCELLED]);
            $this->finalize($order, $grafolog, $admin->id);
        });
        $order = $order->fresh(['user', 'product']);
        $this->notifier->send($grafolog, 'penugasan', 'Penugasan baru', "Anda ditugaskan menangani pesanan #{$order->id} ({$order->product->nama}).", '/grafolog/ditugaskan');
        $this->notifier->send($order->user, 'status_pesanan', 'Grafolog ditugaskan', "Pesanan #{$order->id} ditangani oleh {$grafolog->name}.", '/riwayat');
    }

    /** Permintaan yang lewat 24 jam dianggap ditolak. Dipanggil terjadwal + saat daftar dibuka. */
    public function sweep(): int
    {
        $this->remind();
        $due = GrafologRequest::where('status', GrafologRequest::WAITING)->where('expires_at', '<', now())->get();
        $due->each(fn (GrafologRequest $r) => $this->release($r, GrafologRequest::EXPIRED, 'Tidak menjawab dalam '.self::CONFIRM_HOURS.' jam.'));

        return $due->count();
    }

    /** Pengingat sekali per permintaan saat sisa waktu <= 12 jam (inbox + email ke grafolog). */
    public function remind(): int
    {
        $due = GrafologRequest::where('status', GrafologRequest::WAITING)->whereNull('reminded_at')
            ->where('expires_at', '>', now())->where('expires_at', '<=', now()->addHours(self::REMIND_WHEN_HOURS_LEFT))
            ->with(['order.product', 'order.user', 'grafolog'])->get();
        foreach ($due as $req) {
            $req->update(['reminded_at' => now()]);
            $left = max(1, (int) ceil(now()->diffInMinutes($req->expires_at, false) / 60));
            $this->notifier->send($req->grafolog, 'pengingat_permintaan', 'Pengingat: permintaan menunggu jawaban Anda',
                "Pesanan #{$req->order_id} ({$req->order->product?->nama}) dari {$req->order->user?->name} tinggal ± {$left} jam lagi. Terima atau tolak agar klien tidak menunggu.", '/grafolog/ditugaskan');
        }

        return $due->count();
    }

    /** Rekomendasi (urut skor) untuk membantu administrator: ketersediaan, keahlian, kota (offline), beban, pengalaman. */
    public function recommend(Order $order, int $limit = 5): array
    {
        $month = self::monthOf($order);
        $needle = mb_strtolower($order->product->nama.' '.$order->product->ringkasan);
        $declined = $order->grafologRequests()->whereIn('status', [GrafologRequest::DECLINED, GrafologRequest::EXPIRED])->pluck('grafolog_id');

        return User::where('role', 'grafolog')->where(fn ($q) => $q->whereNull('grafolog_status')->orWhere('grafolog_status', 'verified'))
            ->whereNotIn('id', $declined)
            ->withCount(['assignments as active_assignments' => fn ($q) => $q->where('status', 'assigned'),
                'serviceDeliveries as active_services' => fn ($q) => $q->whereIn('status', ['ditugaskan', 'dijadwalkan'])])
            ->get(['id', 'name', 'city', 'specializations', 'experience_years'])
            ->map(function (User $g) use ($order, $month, $needle) {
                $av = GrafologAvailability::forMonth($g, $month);
                if (! $av['bookable']) {
                    return null;
                }
                $score = 0;
                $why = ['tersedia '.$month];
                foreach ((array) $g->specializations as $s) {
                    if ($s && str_contains($needle, mb_strtolower($s))) {
                        $score += 3;
                        $why[] = "keahlian: {$s}";
                    }
                }
                if ($order->delivery_mode === 'offline' && $g->city && str_contains(mb_strtolower((string) $order->location_note), mb_strtolower($g->city))) {
                    $score += 4;
                    $why[] = "kota sama ({$g->city})";
                }
                $load = $g->active_assignments + $g->active_services;
                $score += max(0, 5 - $load);
                $why[] = "beban aktif {$load}";
                $score += min(3, intdiv((int) $g->experience_years, 5));

                return ['id' => $g->id, 'name' => $g->name, 'city' => $g->city, 'experience_years' => $g->experience_years, 'score' => $score, 'why' => $why, 'slots_left' => $av['slots_left']];
            })->filter()->sortByDesc('score')->take($limit)->values()->all();
    }

    private function release(GrafologRequest $req, string $status, ?string $note): void
    {
        $order = $req->order()->with(['user', 'product'])->first();
        $grafolog = $req->grafolog;
        DB::transaction(function () use ($req, $status, $note, $order) {
            $req->update(['status' => $status, 'note' => $note ? mb_substr($note, 0, 255) : null, 'responded_at' => now()]);
            $order->update(['grafolog_id' => null, 'grafolog_attempts' => $order->grafolog_attempts + 1, 'grafolog_deadline' => null, 'grafolog_state' => 'menunggu_pilih']);
        });
        $order->refresh();
        $why = $status === GrafologRequest::EXPIRED ? 'tidak menjawab dalam batas waktu' : 'menolak';
        if ($order->grafolog_attempts >= self::MAX_ATTEMPTS) {
            $this->toAdmin($order);

            return;
        }
        $this->notifier->send($order->user, 'status_pesanan', 'Pilih grafolog lain',
            "{$grafolog->name} {$why} untuk pesanan #{$order->id}. Silakan pilih grafolog lain atau minta rekomendasi tim kami.", '/riwayat');
    }

    /** Menetapkan grafolog: sampel ditugaskan, layanan pendukung diserahkan. */
    private function finalize(Order $order, User $grafolog, int $assignedBy): void
    {
        $order->update(['grafolog_id' => $grafolog->id, 'grafolog_state' => 'diterima', 'grafolog_deadline' => null]);
        if ($order->sample_id) {
            Assignment::updateOrCreate(['sample_id' => $order->sample_id], ['grafolog_id' => $grafolog->id, 'assigned_by' => $assignedBy, 'status' => 'assigned']);
        }
        $order->deliveries()->whereIn('status', ['menunggu_penugasan', 'ditugaskan'])->update(['assigned_to' => $grafolog->id, 'status' => 'ditugaskan']);
    }
}
