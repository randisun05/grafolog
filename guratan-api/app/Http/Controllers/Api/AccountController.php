<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\CvValue;
use App\Models\GrafologBankAccount;
use App\Models\GrafologEarning;
use App\Models\HandwritingSample;
use App\Models\InboxMessage;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\PersonalityReport;
use App\Models\ReportValidation;
use App\Models\SampleImage;
use App\Models\ServiceDelivery;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hak subjek data UU PDP, swalayan (2026-10-19): persetujuan ulang, ekspor data, hapus akun
 * (anonimisasi). Data transaksi & laporan yang wajib disimpan tetap ada tanpa identitas pribadi.
 */
class AccountController extends Controller
{
    /** Terima ToS/Privasi versi terbaru. */
    public function acceptTerms(Request $request): JsonResponse
    {
        $request->validate(['accept' => ['accepted']]);
        $user = $request->user();
        $user->update(['consented_at' => now(), 'consent_source' => 'self', 'consent_version' => User::CONSENT_VERSION]);
        AuditLog::record('setujui_syarat', User::class, $user->id, $user->id, $request->ip());

        return response()->json(['user' => $user->fresh()]);
    }

    /** Unduh salinan data pribadi (JSON). */
    public function export(Request $request): Response
    {
        $user = $request->user();
        $sampleIds = HandwritingSample::where('user_id', $user->id)->pluck('id');

        $data = [
            'diekspor_pada' => now()->toIso8601String(),
            'akun' => $user->only(['id', 'name', 'email', 'phone', 'role', 'city', 'bio', 'specializations', 'experience_years', 'birth_date', 'gender', 'education', 'occupation', 'email_notifications', 'created_at']),
            'persetujuan' => $user->only(['consented_at', 'consent_source', 'consent_version']),
            'cv' => CvValue::where('user_id', $user->id)->with('field:id,key,label,type')->get()
                ->map(fn ($v) => ['variabel' => $v->field?->label, 'kunci' => $v->field?->key, 'nilai' => $v->field?->isFile() ? '(berkas, unduh lewat aplikasi)' : $v->value])->all(),
            'pesanan' => Order::where('user_id', $user->id)->with('product:id,nama')->get()
                ->map(fn (Order $o) => ['id' => $o->id, 'produk' => $o->product?->nama, 'status' => $o->status, 'jumlah' => $o->amount, 'dibuat' => $o->created_at, 'dibayar' => $o->paid_at])->all(),
            'sampel_tulisan' => HandwritingSample::whereIn('id', $sampleIds)->withCount('images')->get(['id', 'tier', 'status', 'created_at'])->toArray(),
            'laporan' => PersonalityReport::whereIn('sample_id', $sampleIds)->where('review_status', PersonalityReport::REVIEW_APPROVED)->get(['id', 'sample_id', 'tier', 'data', 'generated_at'])->toArray(),
            'pesan_chat' => OrderMessage::where('sender_id', $user->id)->get(['order_id', 'body', 'created_at'])->toArray(),
            'kotak_masuk' => InboxMessage::where('user_id', $user->id)->get(['type', 'title', 'body', 'read_at', 'created_at'])->toArray(),
            'log_aktivitas_90_hari' => ActivityLog::where('user_id', $user->id)->latest('id')->limit(1000)->get(['event', 'method', 'path', 'status', 'ip', 'created_at'])->toArray(),
        ];
        if ($user->role === 'grafolog') {
            $b = GrafologBankAccount::where('user_id', $user->id)->first();
            $data['rekening_pencairan'] = $b ? ['bank' => $b->bank_name, 'nomor' => $b->account_number, 'atas_nama' => $b->account_holder] : null;
            $data['komisi'] = GrafologEarning::where('grafolog_id', $user->id)->get(['order_id', 'amount', 'status', 'earned_at'])->toArray();
            $data['grafolog'] = ['saldo_token' => $user->token_balance, 'nomor_sertifikat' => $user->certificate_number];
        }
        AuditLog::record('ekspor_data_pribadi', User::class, $user->id, $user->id, $request->ip());

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'data-guratan-'.$user->id.'.json',
            ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
        );
    }

    /** Hapus akun = anonimisasi. Ditolak bila masih ada kewajiban berjalan. */
    public function destroy(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'in:HAPUS'],
            'forfeit_tokens' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        abort_unless(Hash::check($data['password'], $user->password), 422, 'Kata sandi salah.');
        $this->assertDeletable($user, (bool) ($data['forfeit_tokens'] ?? false));

        DB::transaction(function () use ($user) {
            Order::where('user_id', $user->id)->where('status', Order::STATUS_UNPAID)->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => 'Akun dihapus pengguna.']);

            foreach (CvValue::where('user_id', $user->id)->with('field')->get() as $v) {
                $meta = $v->field?->isFile() && $v->value ? json_decode($v->value, true) : null;
                if ($meta['path'] ?? null) {
                    Storage::disk('local')->delete($meta['path']);
                }
            }
            CvValue::where('user_id', $user->id)->delete();
            GrafologBankAccount::where('user_id', $user->id)->delete();

            $sampleIds = HandwritingSample::where('user_id', $user->id)->pluck('id');
            foreach (SampleImage::whereIn('sample_id', $sampleIds)->get() as $img) {
                Storage::disk(SampleImage::DISK)->delete($img->path);
                $img->delete();
            }
            if ($user->certificate_path) {
                Storage::disk('local')->delete($user->certificate_path);
            }
            OrderMessage::where('sender_id', $user->id)->update(['body' => '[pesan dihapus]']);
            InboxMessage::where('user_id', $user->id)->delete();
            ActivityLog::where('user_id', $user->id)->update(['user_id' => null]);
            $user->tokens()->delete();

            $user->forceFill([
                'name' => 'Pengguna Terhapus', 'email' => "deleted+{$user->id}@deleted.invalid", 'phone' => null,
                'password' => Str::random(40), 'remember_token' => null, 'city' => null, 'bio' => null, 'specializations' => null,
                'experience_years' => null, 'birth_date' => null, 'gender' => null, 'education' => null, 'occupation' => null,
                'certificate_number' => null, 'certificate_path' => null, 'token_balance' => 0,
                'email_notifications' => false, 'must_change_password' => false, 'anonymized_at' => now(),
            ])->save();
        });
        AuditLog::record('hapus_akun', User::class, $user->id, null, $request->ip());
        $notifier->toAdmins('akun_dihapus', 'Akun dihapus pengguna', "Akun #{$user->id} ({$user->role}) dianonimkan atas permintaan pemilik.");

        return response()->json(['message' => 'Akun Anda telah dihapus.']);
    }

    private function assertDeletable(User $user, bool $forfeitTokens): void
    {
        abort_if($user->isAdministrator(), 422, 'Akun administrator dikelola administrator lain.');
        abort_if($user->isHr(), 422, 'Akun perusahaan dikelola administrator - hubungi kami untuk penghapusan.');
        abort_if($user->role === 'user' && $user->acquisition_source === 'b2b', 422, 'Akun kandidat dikelola perusahaan yang mendaftarkan Anda. Hubungi perusahaan atau administrator.');

        if ($user->role === 'user') {
            abort_if(Order::where('user_id', $user->id)->where('status', Order::STATUS_PROCESSING)->exists(), 422, 'Masih ada pesanan yang sedang diproses. Tunggu selesai atau hubungi administrator.');
        }
        if ($user->role === 'grafolog') {
            $busy = Assignment::where('grafolog_id', $user->id)->where('status', 'assigned')->exists()
                || ServiceDelivery::where('assigned_to', $user->id)->whereIn('status', ['ditugaskan', 'dijadwalkan'])->exists()
                || ReportValidation::whereIn('status', ReportValidation::ACTIVE)->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('validator_id', $user->id))->exists();
            abort_if($busy, 422, 'Masih ada penugasan atau supervisi yang berjalan. Selesaikan dulu atau hubungi administrator.');
            abort_if($user->token_balance > 0 && ! $forfeitTokens, 422, "Saldo {$user->token_balance} token akan hangus dan tidak dapat diuangkan. Kirim ulang dengan forfeit_tokens=true untuk melanjutkan.");
        }
    }
}
