<?php

namespace App\Support\Dcs\Pdf;

/**
 * The boundary to whatever draws the PDF of a document. Today that is the Node
 * application's engine (so old and new documents share one layout); a PHP
 * engine could replace it later without touching the actions.
 */
interface PdfEngine
{
    /**
     * Composes the PDF of a document version from its structured content.
     *
     * @param  array<string, mixed>  $input  documentNumber, title, docTypeCode, department, major, minor, purpose, scope,
     *                                       sections, flow, letterhead, signatureGroups, revisionHistory, appendix, margins,
     *                                       headerStyle, docTypeName, documentLevel, processOwner, language, issuedDateIso,
     *                                       statusLabel, confidentialityNotice; binary parts (flow image, logo) are raw bytes
     * @return array{pdf: string, signatureSheetPageIndex: int|null, sigLayout: array<string, mixed>|null, headerLayout: array<string, mixed>|null}
     *
     * @throws PdfEngineException
     */
    public function compose(array $input): array;

    /**
     * Stamps one signature on a PDF and returns the new bytes.
     *
     * @param  array<string, mixed>  $input  mode ("column" fills a box of the composed signature sheet,
     *                                       "generic" stamps one block on a plain uploaded PDF), pdf (raw bytes),
     *                                       signerName, signerRole, signedAtIso; for "column" also groups,
     *                                       pageIndex, columnIndex, sigLayout
     *
     * @throws PdfEngineException
     */
    public function stampSignature(array $input): string;

    /**
     * Stamps the live-status watermark, provenance footer and header cells on a
     * copy of a PDF for one viewer. The stored file is never changed.
     *
     * @param  array<string, mixed>  $input  pdf (raw bytes), status, viewerName, accessedAt (text), documentNumber,
     *                                       major, minor, expired, headerLayout (array|null), issuedDateIso (string|null)
     *
     * @throws PdfEngineException
     */
    public function watermark(array $input): string;
}
