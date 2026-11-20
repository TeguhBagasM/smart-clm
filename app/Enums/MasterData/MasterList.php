<?php

namespace App\Enums\MasterData;

use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Currency;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\MasterData\TenantPlatform;
use Illuminate\Database\Eloquent\Model;

/**
 * The master data lists a company manages. Each case is its own typed table
 * (this is a catalog of them, not a settings engine). The name shown to people
 * lives in config/master_data.php.
 */
enum MasterList: string
{
    case Platform = 'platforms';
    case Category = 'categories';
    case DocumentType = 'document-types';
    case EmploymentType = 'employment-types';
    case PartyType = 'party-types';
    case Currency = 'currencies';
    case PartnerGroup = 'partner-groups';
    case Partner = 'partners';

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Platform => TenantPlatform::class,
            self::Category => ContractCategory::class,
            self::DocumentType => ContractDocumentType::class,
            self::EmploymentType => EmploymentType::class,
            self::PartyType => PartyType::class,
            self::Currency => Currency::class,
            self::PartnerGroup => PartnerGroup::class,
            self::Partner => Partner::class,
        };
    }

    public function label(): string
    {
        return config()->string("master_data.lists.{$this->value}");
    }

    /**
     * Whether rows carry a short code next to the name (and is_system).
     */
    public function hasCode(): bool
    {
        return ! in_array($this, [self::PartnerGroup, self::Partner], true);
    }

    /**
     * Longest code the table accepts.
     */
    public function codeLength(): int
    {
        return match ($this) {
            self::Platform => 50,
            self::DocumentType => 20,
            self::Currency => 10,
            default => 100,
        };
    }

    /**
     * The lists whose create/update differ only by code, name and a fixed flag
     * (one shared action, SaveLookupRecord); the others have relations of their own.
     */
    public function isSimple(): bool
    {
        return in_array($this, [self::Platform, self::Category, self::EmploymentType, self::PartyType, self::Currency], true);
    }

    public static function fromModel(Model $record): self
    {
        foreach (self::cases() as $list) {
            if ($record instanceof ($list->model())) {
                return $list;
            }
        }

        throw new \InvalidArgumentException($record::class.' is not a master data model.');
    }
}
