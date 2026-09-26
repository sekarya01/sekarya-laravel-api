<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\Task\CancelTaskAction;
use App\Data\User\DeleteAccountData;
use App\Enums\ActivityStatus;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletTopupStatus;
use App\Enums\WalletWithdrawalStatus;
use App\Exceptions\Domain\AccountHasActiveObligationsException;
use App\Exceptions\Domain\InvalidCurrentPasswordException;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\WalletTopup;
use App\Models\WalletWithdrawal;
use App\Support\TokenIssuer;
use App\Support\VerificationDocuments;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hapus akun: soft-delete + anonimisasi (G3).
 *
 * Buku besar, reputasi, dan task lama TETAP UTUH — menghapusnya berarti
 * merusak laporan keuangan dan rating orang yang pernah bekerja dengannya.
 * Yang hilang adalah data PRIBADI: nama, email, nomor HP, alamat, foto,
 * bio, dan dokumen identitas. Akun yang sudah dihapus tidak bisa login lagi
 * (global scope SoftDeletes + token dicabut).
 *
 * Dua hal dibereskan sebelum anonimisasi, bukan sekadar ditinggalkan:
 * task `draft`/`open` miliknya DIBATALKAN (dananya dikembalikan ke saldo),
 * dan penawaran yang masih menggantung DITARIK. Sisa tanggungan yang tidak
 * bisa dibereskan sendiri (pekerjaan berjalan, permintaan dompet menggantung)
 * MENOLAK penghapusan — lihat `AccountHasActiveObligationsException`.
 */
final class DeleteAccountAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CancelTaskAction $cancelTask,
        private readonly TokenIssuer $tokens,
    ) {}

    public function handle(DeleteAccountData $data, User $user): void
    {
        if (! Hash::check($data->password, $user->password)) {
            throw InvalidCurrentPasswordException::make();
        }

        $this->db->transaction(function () use ($user): void {
            $id = (int) $user->getKey();

            $this->guardAgainstObligations($id);

            // Task yang belum mengikat siapa pun: batalkan, dana kembali ke saldo.
            Task::query()
                ->where('poster_id', $id)
                ->whereIn('status', [TaskStatus::Draft, TaskStatus::Open])
                ->get()
                ->each(fn (Task $task) => $this->cancelTask->handle($task, $user, 'Akun dihapus'));

            // Penawaran menggantung tidak boleh tinggal di meja orang lain.
            Bid::query()
                ->where('bidder_id', $id)
                ->where('status', BidStatus::Pending)
                ->update([
                    'status' => BidStatus::Withdrawn,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->anonymizeVerifications($id);
            $this->anonymize($user);

            $this->tokens->revokeAll($user);

            $user->delete();
        });
    }

    private function guardAgainstObligations(int $id): void
    {
        $reasons = [];

        $hasActiveTasks = Task::query()
            ->where('poster_id', $id)
            ->whereIn('status', [
                TaskStatus::Dealt,
                TaskStatus::Active,
                TaskStatus::Submitted,
                TaskStatus::Disputed,
            ])
            ->exists();

        if ($hasActiveTasks) {
            $reasons[] = 'active_tasks_as_poster';
        }

        $hasActiveWork = Activity::query()
            ->where('worker_id', $id)
            ->whereIn('status', [
                ActivityStatus::OnTheWay,
                ActivityStatus::Arrived,
                ActivityStatus::InProgress,
                ActivityStatus::Submitted,
            ])
            ->exists();

        if ($hasActiveWork) {
            $reasons[] = 'active_work_as_worker';
        }

        $hasPendingTopups = WalletTopup::query()
            ->where('user_id', $id)
            ->where('status', WalletTopupStatus::AwaitingConfirmation)
            ->exists();

        if ($hasPendingTopups) {
            $reasons[] = 'pending_topups';
        }

        $hasPendingWithdrawals = WalletWithdrawal::query()
            ->where('user_id', $id)
            ->where('status', WalletWithdrawalStatus::Requested)
            ->exists();

        if ($hasPendingWithdrawals) {
            $reasons[] = 'pending_withdrawals';
        }

        if ($reasons !== []) {
            throw AccountHasActiveObligationsException::because($reasons);
        }
    }

    /** NIK, rekening, dan path foto dibersihkan; berkas privatnya dihapus. */
    private function anonymizeVerifications(int $id): void
    {
        UserVerification::query()
            ->where('user_id', $id)
            ->get()
            ->each(function (UserVerification $verification): void {
                foreach ([$verification->id_card_photo_path, $verification->selfie_photo_path] as $path) {
                    if (is_string($path) && $path !== '') {
                        Storage::disk(VerificationDocuments::DISK)->delete($path);
                    }
                }

                $verification->forceFill([
                    'id_card_photo_path' => null,
                    'selfie_photo_path' => null,
                    'face_match_score' => null,
                    'document_number_hash' => null,
                    'document_number_enc' => null,
                    'name_on_document' => null,
                    'birth_date_on_document' => null,
                    'bank_code' => null,
                    'account_number_enc' => null,
                    'account_number_last4' => null,
                    'account_holder_name' => null,
                ])->save();
            });
    }

    private function anonymize(User $user): void
    {
        $id = (int) $user->getKey();

        // Email/username wajib unik; placeholder deterministik dari id.
        $user->forceFill([
            'name' => 'Akun Dihapus',
            'first_name' => null,
            'last_name' => null,
            'username' => null,
            'gender' => null,
            'birth_date' => null,
            'email' => "deleted+{$id}@sekarya.invalid",
            'phone' => null,
            'phone_verified_at' => null,
            'avatar_path' => null,
            'bio' => null,
            'address_line' => null,
            'city' => null,
            'province' => null,
            'postal_code' => null,
            'remember_token' => null,
            'password' => Hash::make(Str::random(40)),
        ])->save();

        $user->workerProfile?->forceFill([
            'display_name' => null,
            'headline' => null,
            'contact_phone' => null,
            'avatar_path' => null,
            'address_line' => null,
            'city' => null,
            'province' => null,
            'postal_code' => null,
            'latitude' => null,
            'longitude' => null,
            'is_available' => false,
        ])->save();
    }
}
