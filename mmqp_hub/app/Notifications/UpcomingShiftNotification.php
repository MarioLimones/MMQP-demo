<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class UpcomingShiftNotification extends Notification
{
    use Queueable;

    public $shiftDate;
    public $shiftTime;

    /**
     * Create a new notification instance.
     */
    public function __construct($shiftDate, $shiftTime)
    {
        $this->shiftDate = $shiftDate;
        $this->shiftTime = $shiftTime;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Próximo turno')
            ->icon('/favicon.ico')
            ->body("Tienes un turno programado el {$this->shiftDate} a las {$this->shiftTime}.")
            ->action('Ver Horario', 'ver_horario')
            ->data(['url' => url('/dashboard')]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
