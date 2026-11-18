<?php

namespace App\Support\Dcs;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders errors of the DCS API in the shape the existing React client reads:
 * `{ "error": "<message>" }`, with `errors` (field => messages) added for
 * validation failures. Validation answers 400 like the Node API did, not
 * Laravel's default 422. Other routes keep Laravel's default rendering.
 */
class DcsErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/dcs', 'api/dcs/*', 'api/dcs-external-review/*', 'api/ai/extract-dcs-metadata')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(
                $e->validator->errors()->first(),
                400,
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => $this->respond('Sesi tidak valid atau kadaluarsa. Silakan login.', 401),
            $e instanceof HttpExceptionInterface => $this->respond($this->httpMessage($e), $e->getStatusCode()),
            default => $this->respond('Terjadi kesalahan pada server.', 500),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function respond(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['error' => $message, ...$extra], $status);
    }

    /**
     * Laravel has already turned authorization failures and missing models into
     * HTTP exceptions by now. Framework texts (404 messages, the default
     * authorization text) are replaced by the Indonesian defaults; messages
     * written for the user (DcsException, policies, `abort()`) are kept.
     */
    private function httpMessage(HttpExceptionInterface $e): string
    {
        $message = $e->getMessage();

        if ($e instanceof DcsException) {
            return $message;
        }

        $isFrameworkText = $message === '' || $message === 'This action is unauthorized.' || $e->getStatusCode() === 404;

        return $isFrameworkText ? $this->defaultMessage($e->getStatusCode()) : $message;
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            401 => 'Sesi tidak valid atau kadaluarsa. Silakan login.',
            403 => 'Anda tidak memiliki izin untuk tindakan ini.',
            404 => 'Data tidak ditemukan.',
            405 => 'Metode tidak didukung.',
            409 => 'Permintaan bentrok dengan keadaan data saat ini.',
            413 => 'Berkas terlalu besar.',
            429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
            default => 'Permintaan tidak dapat diproses.',
        };
    }
}
