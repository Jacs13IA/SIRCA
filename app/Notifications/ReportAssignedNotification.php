<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Report $report) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Nuevo reporte asignado',
            'body' => "Se te asignó el reporte {$this->report->folio}.",
            'folio' => $this->report->folio,
            'url' => route('reports.show', $this->report),
        ];
    }
}