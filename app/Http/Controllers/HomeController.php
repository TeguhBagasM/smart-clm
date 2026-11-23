<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The root address goes to the dashboard. Review links sent before the move to this app were
 * `/?dcsReviewToken=...`; they still lead to the review page.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('dcsReviewToken');

        if (is_string($token) && preg_match('/^[A-Za-z0-9]{16,128}$/', $token) === 1) {
            return redirect()->route('external-review.show', ['token' => $token]);
        }

        return redirect('/dashboard');
    }
}
