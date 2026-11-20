<?php

namespace Tests\Support;

use App\Enums\MasterData\MasterList;
use App\Http\Requests\MasterData\SaveMasterDataRequest;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Currency;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

/**
 * Builds one row of any master data list for a company, and runs a form
 * request the way a controller would, without needing a route.
 */
class MasterDataFixtures
{
    public static function row(MasterList $list, Tenant $tenant, array $attributes = []): Model
    {
        return match ($list) {
            MasterList::Platform => TenantPlatform::factory()->for($tenant)->create($attributes),
            MasterList::Category => ContractCategory::factory()->for($tenant)->create($attributes),
            MasterList::DocumentType => ContractDocumentType::factory()->for($tenant)->create($attributes),
            MasterList::EmploymentType => EmploymentType::factory()->for($tenant)->create($attributes),
            MasterList::PartyType => PartyType::factory()->for($tenant)->create($attributes),
            MasterList::Currency => Currency::factory()->for($tenant)->create($attributes),
            MasterList::PartnerGroup => PartnerGroup::factory()->for($tenant)->create($attributes),
            MasterList::Partner => Partner::factory()->for(PartnerGroup::factory()->for($tenant), 'group')->create($attributes),
        };
    }

    /**
     * @param  class-string<SaveMasterDataRequest>  $class
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $parameters  extra route parameters (e.g. list)
     */
    public static function request(string $class, User $user, array $data, ?Model $record = null, array $parameters = []): SaveMasterDataRequest
    {
        $request = $class::create('/master-data', 'POST', $data);
        $request->setUserResolver(fn () => $user);

        if ($record !== null || $parameters !== []) {
            $route = new Route('PUT', '/master-data/{record?}/{list?}', []);
            $route->bind($request);

            foreach (($record !== null ? ['record' => $record] : []) + $parameters as $name => $value) {
                $route->setParameter($name, $value);
            }

            $request->setRouteResolver(fn () => $route);
        }

        return $request;
    }

    /**
     * The validation errors the form would show, by field.
     *
     * @param  class-string<SaveMasterDataRequest>  $class
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    public static function errors(string $class, User $user, array $data, ?Model $record = null, array $parameters = []): array
    {
        $request = self::request($class, $user, $data, $record, $parameters);

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->errors()->toArray();
    }
}
