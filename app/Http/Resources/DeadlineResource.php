<?php

namespace App\Http\Resources;

use App\Enums\AdherentReport;
use App\Enums\DeadlineStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une échéance calculée par App\Support\Deadlines (tableau), avec ce qui a été enregistré.
 */
class DeadlineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DeadlineStatus $status */
        $status = $this->resource['status'];
        /** @var AdherentReport|null $report */
        $report = $this->resource['adherent_report'];
        $obligation = $this->resource['obligation'];
        $adherent = $this->resource['adherent'];

        return [
            'key' => $obligation->id.'|'.$this->resource['due_date'],
            'adherent' => [
                'id' => $adherent->id,
                'company_name' => $adherent->company_name,
                'member_no' => $adherent->member_no,
            ],
            'obligation' => [
                'id' => $obligation->id,
                'code' => $obligation->code,
                'label' => $obligation->label,
                'administration' => ['code' => $obligation->administration_code, 'name' => $obligation->administration_name],
                'platform' => $obligation->platform,
            ],
            'periodicity' => $this->resource['periodicity'],
            'due_date' => $this->resource['due_date'],
            'days_left' => $this->resource['days_left'],
            'overdue' => $this->resource['overdue'],
            'status' => $status,
            'status_label' => $status->label(),
            'amount_due' => $this->resource['amount_due'],
            'amount_paid' => $this->resource['amount_paid'],
            'balance' => $this->resource['amount_due'] === null ? null : max(0, $this->resource['amount_due'] - ($this->resource['amount_paid'] ?? 0)),
            'adherent_report' => $report === null ? null : [
                'type' => $report,
                'label' => $report->label(),
                'amount' => $this->resource['adherent_report_amount'],
                'reported_at' => $this->resource['adherent_reported_at'],
            ],
        ];
    }
}
