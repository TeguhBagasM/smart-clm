<?php

namespace App\Support\Templates;

/**
 * The words sent to the model for a clause draft. Only the fixed instructions, the
 * global field names (config, the same for every company) and the person's own
 * description go in. Nothing about a company, a vendor, an employee or a contract
 * does (audit A2.5). The description is data: it sits between markers, and the
 * instructions say to ignore any instruction inside it.
 */
class ClauseDraftPrompt
{
    public const OPEN = '<permintaan>';

    public const CLOSE = '</permintaan>';

    public static function system(): string
    {
        $fields = array_map(fn (array $variable): string => '{{'.$variable['code'].'}}', config()->array('contract_templates.defaults.variables'));

        return strtr(config()->string('contract_templates.ai.system'), [':fields' => implode(', ', $fields)]);
    }

    public static function user(string $description): string
    {
        return self::OPEN."\n".$description."\n".self::CLOSE."\n\n".config()->string('contract_templates.ai.task');
    }

    /**
     * Removes control characters and the markers, so the description cannot close its own block.
     */
    public static function clean(string $description): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $description);

        return trim(str_ireplace([self::OPEN, self::CLOSE], '', $text));
    }
}
