<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\VendorAuditSnapshot;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a vendor of a company. The name is unique per company
 * (ignoring letter case). The account number is replaced only when the form
 * says so (`replace_bank_account`), since the screen never receives it back.
 * The audit entry names the sensitive columns that changed, not their values.
 */
class SaveVendor
{
    public function __construct(private RecordAuditLog $recordAuditLog, private VendorAuditSnapshot $snapshot) {}

    /**
     * @param  array{name: string, npwp?: string|null, address?: string|null, pic_name?: string|null, pic_phone?: string|null, email?: string|null, bank_name?: string|null, bank_branch?: string|null, bank_account_name?: string|null, pph_rate_percent?: string|null, replace_bank_account?: bool, bank_account_number?: string|null}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?Vendor $vendor = null): Vendor
    {
        if ($vendor !== null && $vendor->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(Vendor::class);
        }

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, $vendor): Vendor {
                $before = $vendor === null ? null : $this->snapshot->of($vendor);
                $vendor ??= new Vendor;
                $fields = ['name' => trim($data['name']), 'updated_by_id' => $actor->id];

                foreach (['npwp', 'address', 'pic_name', 'pic_phone', 'email', 'bank_name', 'bank_branch', 'bank_account_name', 'pph_rate_percent'] as $column) {
                    $fields[$column] = $data[$column] ?? null;
                }

                if (($data['replace_bank_account'] ?? false) || ! $vendor->exists) {
                    $fields['bank_account_number'] = $data['bank_account_number'] ?? null;
                }

                if (! $vendor->exists) {
                    $fields += ['tenant_id' => $tenant->id, 'is_active' => true, 'created_by_id' => $actor->id];
                }

                $vendor->forceFill($fields);
                $changed = $this->snapshot->dirtySensitive($vendor);
                $vendor->save();
                $this->recordAuditLog->handle($actor, 'master_data.vendors.'.($before === null ? 'created' : 'updated'), $vendor, $before, $this->snapshot->of($vendor->refresh(), $changed), $tenant->id);

                return $vendor;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => config()->string('vendors.messages.duplicate_name')]);
        }
    }
}
