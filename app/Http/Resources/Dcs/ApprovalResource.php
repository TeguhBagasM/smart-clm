<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\Approval;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signature in Node's shape. Loads `approver` for the legacy user id.
 *
 * @mixin Approval
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'approverUserId' => $this->approver->apiId(),
            'approverRole' => $this->approver_role_key,
            'decision' => 'approved',
            'decidedAt' => ApiDate::format($this->decided_at),
            'signedContentSha256' => $this->signed_content_sha256,
        ];
    }
}
