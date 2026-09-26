<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis peristiwa yang dikirim lewat push.
 *
 * Nilainya ikut di payload FCM sebagai `data.type`, dan itulah yang dibaca
 * klien untuk memutuskan ke layar mana notifikasi membuka. Karena itu ia
 * adalah KONTRAK: mengganti nilainya berarti klien lama berhenti mengenalinya.
 *
 * Jenis yang belum dikenal klien harus diperlakukan sebagai notifikasi biasa
 * yang tetap boleh dibuka — bukan diabaikan — supaya menambah jenis baru di
 * server tidak membuat notifikasi lama berhenti berfungsi.
 */
enum PushType: string
{
    case BidPlaced = 'bid_placed';
    case BidAccepted = 'bid_accepted';
    case ActivityOnTheWay = 'activity_on_the_way';
    case ActivityArrived = 'activity_arrived';
    case ActivityInProgress = 'activity_in_progress';
    case ActivitySubmitted = 'activity_submitted';
    case ActivityApproved = 'activity_approved';
    case ActivityRejected = 'activity_rejected';
    // Pembatalan ber-persetujuan (U12).
    case CancelRequested = 'cancel_requested';
    case CancelRequestResolved = 'cancel_request_resolved';
    case TaskCancelled = 'task_cancelled';
    // Tugas baru ke mitra terdekat yang tersedia (B13).
    case TaskPublished = 'task_published';
    // Batas waktu penawaran terlewat (G12).
    case TaskExpired = 'task_expired';
    // Penawaran gugur karena lelang ditutup (G12).
    case BidExpired = 'bid_expired';
    // Sengketa diputuskan pengelola (G5).
    case DisputeResolved = 'dispute_resolved';
    // Dompet: hasil review permintaan isi saldo / penarikan (G11).
    case TopupConfirmed = 'topup_confirmed';
    case TopupRejected = 'topup_rejected';
    case WithdrawalCompleted = 'withdrawal_completed';
    case WithdrawalRejected = 'withdrawal_rejected';
}
