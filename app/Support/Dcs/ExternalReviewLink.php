<?php

namespace App\Support\Dcs;

use Illuminate\Http\Request;

/**
 * The address an outside party opens to review a version. It starts with `DCS_SHARE_BASE_URL`
 * (the public address) when set, else with the address the owner is using.
 */
class ExternalReviewLink
{
    public static function for(string $token, Request $request): string
    {
        $base = rtrim((string) (config('dcs.share_base_url') ?: $request->getSchemeAndHttpHost()), '/');

        return $base.route('external-review.show', ['token' => $token], false);
    }
}
