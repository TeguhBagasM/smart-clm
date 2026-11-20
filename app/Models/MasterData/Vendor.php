<?php

namespace App\Models\MasterData;

use App\Models\Tenant;
use Database\Factories\MasterData\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A vendor of a company: the second party of external contracts, with contact,
 * tax and bank details (Node `vendors`). The contact, tax and bank columns are
 * sensitive: only holders of vendors.manage see them (VendorResource), the
 * account number is encrypted at rest and never leaves the server in full.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $npwp digits only
 * @property string|null $address
 * @property string|null $pic_name
 * @property string|null $pic_phone
 * @property string|null $email
 * @property string|null $bank_name
 * @property string|null $bank_branch
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number decrypted
 * @property string|null $pph_rate_percent
 * @property bool $is_active
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'npwp', 'address', 'pic_name', 'pic_phone', 'email', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'pph_rate_percent'])]
#[Hidden(['bank_account_number'])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    /** Columns that are never written to logs or the audit trail by value. */
    public const SENSITIVE = ['npwp', 'pic_name', 'pic_phone', 'email', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'pph_rate_percent'];

    protected $table = 'vendors';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The last four digits of the account number, for showing which account is stored.
     */
    public function bankAccountTail(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->bank_account_number);

        return $digits === '' ? null : substr($digits, -4);
    }

    /**
     * The NPWP as people write it: 15 digits as 00.000.000.0-000.000, 16 digits in groups of four.
     */
    public function formattedNpwp(): ?string
    {
        $npwp = (string) $this->npwp;

        return match (strlen($npwp)) {
            15 => vsprintf('%s.%s.%s.%s-%s.%s', [substr($npwp, 0, 2), substr($npwp, 2, 3), substr($npwp, 5, 3), substr($npwp, 8, 1), substr($npwp, 9, 3), substr($npwp, 12, 3)]),
            16 => trim(chunk_split($npwp, 4, ' ')),
            0 => null,
            default => $npwp,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bank_account_number' => 'encrypted', 'pph_rate_percent' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
