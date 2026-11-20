<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Templates\VariableType;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateClause;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Templates\UniqueLibraryName;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a field ({{Code}}) of the company's library. The code is
 * what clauses and templates write, so it cannot change once a clause, a
 * template draft or a published version uses it. Only the name, type and text
 * around it can change then.
 */
class SaveContractVariable
{
    private const AUDITED = ['code', 'name', 'value_type', 'description', 'default_value', 'source_field', 'sort_order'];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{code: string, name: string, value_type: string, description?: string|null, default_value?: string|null, source_field?: string|null}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?ContractVariable $variable = null): ContractVariable
    {
        if ($variable !== null && $variable->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(ContractVariable::class);
        }

        $code = trim($data['code']);
        $name = trim($data['name']);
        $source = $this->nullable($data['source_field'] ?? null);

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', $code) !== 1) {
            throw ValidationException::withMessages(['code' => config()->string('contract_templates.messages.code_format')]);
        }

        if ($source !== null && ! array_key_exists($source, config()->array('contract_templates.source_fields'))) {
            throw ValidationException::withMessages(['source_field' => config()->string('contract_templates.messages.source_unknown')]);
        }

        UniqueLibraryName::ensure(ContractVariable::class, $tenant->id, 'code', $code, $variable, 'code', config()->string('contract_templates.messages.code_taken'));
        UniqueLibraryName::ensure(ContractVariable::class, $tenant->id, 'name', $name, $variable, 'name', config()->string('contract_templates.messages.name_taken'));
        $this->ensureCodeCanChange($tenant, $variable, $code);

        $fields = [
            'code' => $code, 'name' => $name, 'value_type' => VariableType::from($data['value_type']),
            'description' => $this->nullable($data['description'] ?? null), 'default_value' => $this->nullable($data['default_value'] ?? null), 'source_field' => $source,
        ];

        try {
            return DB::transaction(function () use ($actor, $tenant, $fields, $variable): ContractVariable {
                $isNew = $variable === null;
                $before = $isNew ? null : $variable->only(self::AUDITED);
                $variable ??= new ContractVariable;

                if ($isNew) {
                    $order = (int) ContractVariable::query()->where('tenant_id', $tenant->id)->max('sort_order');
                    $variable->forceFill(['tenant_id' => $tenant->id, 'sort_order' => $order + 1, 'is_active' => true, 'is_system' => false, 'created_by_id' => $actor->id]);
                }

                $variable->forceFill($fields + ['updated_by_id' => $actor->id])->save();
                $this->recordAuditLog->handle($actor, 'contract_templates.variables.'.($isNew ? 'created' : 'updated'), $variable, $before, $variable->only(self::AUDITED), $tenant->id);

                return $variable;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => config()->string('contract_templates.messages.code_taken')]);
        }
    }

    private function ensureCodeCanChange(Tenant $tenant, ?ContractVariable $variable, string $code): void
    {
        if ($variable === null || $variable->code === $code) {
            return;
        }

        $placeholder = '%{{'.$variable->code.'}}%';
        $used = $variable->versions()->exists()
            || ContractClause::query()->where('tenant_id', $tenant->id)->where('body', 'like', $placeholder)->exists()
            || TemplateClause::query()->where('body', 'like', $placeholder)
                ->whereHas('version.template', fn ($query) => $query->where('tenant_id', $tenant->id))->exists();

        if ($used) {
            throw ValidationException::withMessages(['code' => strtr(config()->string('contract_templates.messages.code_in_use'), [':code' => '{{'.$variable->code.'}}'])]);
        }
    }

    private function nullable(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
