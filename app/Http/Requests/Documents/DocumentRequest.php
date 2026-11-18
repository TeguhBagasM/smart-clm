<?php

namespace App\Http\Requests\Documents;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The base of the requests made from a document page: the person must be able
 * to see the document (another company's is reported as missing).
 */
abstract class DocumentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('document'));
    }
}
