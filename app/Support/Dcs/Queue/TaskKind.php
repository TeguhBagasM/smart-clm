<?php

namespace App\Support\Dcs\Queue;

/**
 * The kinds of work waiting for a person on controlled documents. Each is one
 * tab of "Tugas saya".
 */
enum TaskKind: string
{
    case Sign = 'sign';
    case Revise = 'revise';
    case Release = 'release';
    case Read = 'read';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Sign => 'Perlu ditandatangani',
            self::Revise => 'Perlu direvisi',
            self::Release => 'Siap diberlakukan',
            self::Read => 'Wajib dibaca',
            self::Review => 'Perlu ditinjau ulang',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sign => 'Dokumen yang kolom tanda tangannya sedang menunggu Anda.',
            self::Revise => 'Draft Anda yang dikembalikan atau punya komentar review yang belum selesai.',
            self::Release => 'Dokumen yang sudah disetujui penuh dan menunggu diberlakukan.',
            self::Read => 'Dokumen yang didistribusikan kepada Anda dan belum Anda konfirmasi.',
            self::Review => 'Dokumen milik Anda yang jadwal tinjau ulangnya sudah dekat atau terlewat.',
        };
    }

    public function emptyText(): string
    {
        return match ($this) {
            self::Sign => 'Tidak ada dokumen yang menunggu tanda tangan Anda.',
            self::Revise => 'Tidak ada draft Anda yang perlu direvisi.',
            self::Release => 'Tidak ada dokumen yang menunggu untuk diberlakukan.',
            self::Read => 'Tidak ada dokumen yang wajib Anda baca.',
            self::Review => 'Tidak ada dokumen Anda yang perlu ditinjau ulang.',
        };
    }

    /**
     * The label of the button that opens the document for this task.
     */
    public function action(): string
    {
        return match ($this) {
            self::Sign => 'Tinjau & tanda tangani',
            self::Revise => 'Buka komentar',
            self::Release => 'Berlakukan',
            self::Read => 'Baca dokumen',
            self::Review => 'Tinjau',
        };
    }
}
