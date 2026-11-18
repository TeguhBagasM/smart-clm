<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentTypeSection;
use App\Models\Dcs\DocumentTypeStep;
use App\Models\Tenant;
use App\Support\Dcs\DocumentTypeDefaults;
use App\Support\Dcs\RoleKeyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes the template parts of a document type: default sections, signature
 * steps, lanes and default clauses. A part left null is not touched; an empty
 * list of signature groups restores the default three signers. Call it inside
 * the caller's transaction.
 */
class SyncDocTypeTemplate
{
    public function __construct(private RoleKeyResolver $roleKeyResolver) {}

    /**
     * @param  array{
     *     sections?: list<array{heading: string, content: string|null}>|null,
     *     groups?: list<array{heading: string|null, columns: list<array{label: string, role: string|null, roleName: string|null}>}>|null,
     *     lanes?: list<string>|null,
     *     defaultClauseIds?: list<string>|null,
     * }  $template
     *
     * @throws ValidationException
     */
    public function handle(Tenant $tenant, DocumentType $type, array $template): void
    {
        if (($template['sections'] ?? null) !== null) {
            $this->writeSections($type, $template['sections']);
        }

        if (($template['groups'] ?? null) !== null) {
            $this->writeSteps($tenant, $type, $template['groups'] === [] ? DocumentTypeDefaults::signatureGroups() : $template['groups']);
        }

        if (($template['lanes'] ?? null) !== null) {
            $type->forceFill(['default_lanes' => $template['lanes']])->save();
        }

        if (($template['defaultClauseIds'] ?? null) !== null) {
            $this->writeDefaultClauses($tenant, $type, $template['defaultClauseIds']);
        }
    }

    /**
     * @param  list<array{heading: string, content: string|null}>  $sections
     */
    private function writeSections(DocumentType $type, array $sections): void
    {
        DocumentTypeSection::query()->where('doc_type_id', $type->id)->delete();

        foreach ($sections as $position => $section) {
            DocumentTypeSection::query()->forceCreate([
                'doc_type_id' => $type->id,
                'position' => $position + 1,
                'heading' => $section['heading'],
                'content' => $section['content'] ?? '',
            ]);
        }
    }

    /**
     * @param  list<array{heading: string|null, columns: list<array{label: string, role: string|null, roleName: string|null}>}>  $groups
     */
    private function writeSteps(Tenant $tenant, DocumentType $type, array $groups): void
    {
        $keys = [];

        foreach ($groups as $group) {
            foreach ($group['columns'] as $column) {
                if ($column['role'] !== null) {
                    $keys[] = $column['role'];
                }
            }
        }

        $roles = $this->roleKeyResolver->resolve($tenant, $keys, 'signatureGroups');

        DocumentTypeStep::query()->where('doc_type_id', $type->id)->delete();

        foreach ($groups as $groupPosition => $group) {
            foreach ($group['columns'] as $position => $column) {
                DocumentTypeStep::query()->forceCreate([
                    'doc_type_id' => $type->id,
                    'group_position' => $groupPosition + 1,
                    'group_heading' => $group['heading'],
                    'position' => $position + 1,
                    'label' => $column['label'],
                    'role_id' => $column['role'] === null ? null : $roles[$column['role']],
                    'role_name' => $column['roleName'],
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $uuids
     */
    private function writeDefaultClauses(Tenant $tenant, DocumentType $type, array $uuids): void
    {
        $clauses = Clause::query()->where('tenant_id', $tenant->id)->whereIn('uuid', $uuids)->pluck('id', 'uuid');
        $unknown = array_diff($uuids, $clauses->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(['defaultClauseIds' => 'Klausul tidak ditemukan: '.implode(', ', $unknown).'.']);
        }

        DB::table('dcs_document_type_default_clauses')->where('doc_type_id', $type->id)->delete();

        foreach ($uuids as $position => $uuid) {
            DB::table('dcs_document_type_default_clauses')->insertOrIgnore([
                'doc_type_id' => $type->id,
                'clause_id' => $clauses[$uuid],
                'position' => $position + 1,
            ]);
        }
    }
}
