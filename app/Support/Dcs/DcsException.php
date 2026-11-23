<?php

namespace App\Support\Dcs;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A DCS failure whose message is written for the user and must reach the screen
 * verbatim (the pages show it as a toast). The messages keep the sentences of the
 * Node application.
 */
class DcsException extends HttpException
{
    public static function notFound(string $message): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    public static function serverError(string $message): self
    {
        return new self(500, $message);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }
}
