#!/usr/bin/env bash
# Captures read-only "golden" responses of the legacy Node DCS API so the Laravel
# endpoints can be compared against them (see CLAUDE.md, DCS migration).
#
# Run it against STAGING with a test account. Responses hold business data, so
# they go to the git-ignored _captured/ folder; never commit them unsanitized.
#
#   BASE_URL=https://staging.example.com COOKIE='clm_token=<jwt>' \
#   DOC_ID=<document uuid> VERSION_ID=<version uuid> tests/golden/capture.sh
set -euo pipefail

: "${BASE_URL:?set BASE_URL}" "${COOKIE:?set COOKIE (clm_token=...)}"
OUT="$(dirname "$0")/_captured"
mkdir -p "$OUT"

capture() {
  local name="$1" path="$2"
  curl --silent --show-error --fail --header "Cookie: ${COOKIE}" "${BASE_URL}/api/dcs${path}" \
    --output "${OUT}/${name}.json" && echo "ok   ${name}" || echo "FAIL ${name} (${path})"
}

capture doc-types "/doc-types"
capture numbering-rule "/numbering-rule"
capture clauses "/clauses"
capture documents "/documents"
capture preview-number "/preview-number?docTypeCode=SOP&department=HRD"

if [[ -n "${DOC_ID:-}" ]]; then capture document "/documents/${DOC_ID}"; fi

if [[ -n "${VERSION_ID:-}" ]]; then
  for part in compose approvals approval-roster comments receipts; do
    capture "version-${part}" "/versions/${VERSION_ID}/${part}"
  done
fi
