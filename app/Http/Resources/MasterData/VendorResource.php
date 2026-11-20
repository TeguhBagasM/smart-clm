<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A vendor as its manager sees it. The contact, tax and bank fields are sent
 * only to someone who may manage vendors; the account number is never sent,
 * only its last four digits.
 *
 * @property Vendor $resource
 */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vendor = $this->resource;
        $gate = Gate::forUser($request->user());
        $manages = $request->user() !== null && $gate->allows('viewAny', Vendor::class);

        $data = [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'address' => $vendor->address,
            'is_active' => $vendor->is_active,
            'usage_count' => (int) ($vendor->getAttribute('usage_count') ?? 0),
            'can' => [
                'update' => $manages && $gate->allows('update', $vendor),
                'deactivate' => $manages && $gate->allows('deactivate', $vendor),
            ],
        ];

        if (! $manages) {
            return $data;
        }

        return $data + [
            'npwp' => $vendor->formattedNpwp(),
            'pic_name' => $vendor->pic_name,
            'pic_phone' => $vendor->pic_phone,
            'email' => $vendor->email,
            'bank_name' => $vendor->bank_name,
            'bank_branch' => $vendor->bank_branch,
            'bank_account_name' => $vendor->bank_account_name,
            'bank_account_tail' => $vendor->bankAccountTail(),
            'pph_rate_percent' => $vendor->pph_rate_percent,
        ];
    }
}
