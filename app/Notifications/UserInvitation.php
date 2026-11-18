<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invites a user created by an administrator to choose their password.
 * The link reuses the password reset broker, so it expires like a reset link
 * and the user can request a new one from "Lupa password". It is queued so
 * an unavailable mail server never fails the request that created the user.
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $token = Password::broker(config('fortify.passwords'))->createToken($notifiable);
        $expiresInMinutes = config('auth.passwords.'.config('fortify.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Undangan akun :app', ['app' => config('app.name')]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Akun Anda di :app telah dibuat oleh administrator.', ['app' => config('app.name')]))
            ->action(__('Atur kata sandi'), route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('Tautan ini berlaku :minutes menit. Setelah itu, gunakan menu "Lupa kata sandi" di halaman masuk.', ['minutes' => $expiresInMinutes]));
    }
}
