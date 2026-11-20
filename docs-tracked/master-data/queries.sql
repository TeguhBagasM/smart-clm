-- Master data audit: read-only queries for the Node PostgreSQL database.
-- Run them in a read-only session. They return distinct values and usage
-- counts only: no salary, no employee names, no NIK, no document content.
-- Paste each result back (Q-number + output).

BEGIN TRANSACTION READ ONLY;

-- Q1. Companies (tenants): id, name, code, active, platforms.
SELECT id,
       data->>'name'                       AS name,
       data->>'code'                       AS code,
       COALESCE(data->>'active', 'true')   AS active,
       data->'platforms'                   AS platforms
FROM tenants
ORDER BY seq;

-- Q2. Contracts per owning tenant vs the company picked in the form.
--     A row where companyId <> tenantId means a contract issued for another PT.
SELECT data->>'tenantId'                     AS tenant_id,
       COALESCE(data->>'companyId', '(kosong)') AS company_id,
       count(*)                              AS contracts
FROM contracts
GROUP BY 1, 2
ORDER BY 1, 2;

-- Q3. External categories (configured list) and how many contracts,
--     clauses and templates use each name.
WITH used AS (
  SELECT data->>'tenantId' AS tenant_id, data->>'category' AS name, 'contracts' AS source FROM contracts
  UNION ALL
  SELECT data->>'tenantId', data->>'category', 'clauses' FROM clauses
  UNION ALL
  SELECT data->>'tenantId', data->>'category', 'templates' FROM templates
)
SELECT COALESCE(c."tenantId", u.tenant_id)   AS tenant_id,
       COALESCE(c.name, u.name)              AS category,
       c.sort_order,
       (c.name IS NOT NULL)                  AS in_configured_list,
       count(*) FILTER (WHERE u.source = 'contracts') AS contracts,
       count(*) FILTER (WHERE u.source = 'clauses')   AS clauses,
       count(*) FILTER (WHERE u.source = 'templates') AS templates
FROM categories c
FULL OUTER JOIN used u ON u.tenant_id = c."tenantId" AND u.name = c.name
GROUP BY 1, 2, 3, 4
ORDER BY 1, 3 NULLS LAST, 2;

-- Q4. Contract document types (configured) per tenant.
SELECT s."tenantId"                    AS tenant_id,
       d->>'id'                        AS id,
       d->>'name'                      AS name,
       d->>'code'                      AS code,
       COALESCE(d->>'appliesTo', 'both') AS applies_to,
       d->'categories'                 AS categories,
       d->'extraCodes'                 AS extra_codes,
       d->>'retentionYears'            AS retention_years,
       (COALESCE(d->>'defaultTemplateId', '') <> '') AS has_default_template,
       (COALESCE(d->>'numberMask', '') <> '')        AS has_number_mask
FROM settings s
CROSS JOIN LATERAL jsonb_array_elements(COALESCE(s.data->'masterData'->'docTypes', '[]'::jsonb)) d
ORDER BY 1, 3;

-- Q5. Values stored on contracts for each classification field, with counts.
--     Values missing from the configured lists are "Lainnya" (typed by hand).
SELECT data->>'tenantId' AS tenant_id, 'docType' AS field, data->>'docType' AS value, count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'platform', data->>'platform', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'operator', data->>'operator', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'employmentType', data->>'employmentType', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'division', data->>'division', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'requestingDivision', data->>'requestingDivision', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'party2Type', data->>'party2Type', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT data->>'tenantId', 'currency', data->>'currency', count(*) FROM contracts GROUP BY 1, 3
UNION ALL
SELECT c.data->>'tenantId', 'copy.status', cp->>'status', count(*)
FROM contracts c CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.data->'copies', '[]'::jsonb)) cp
GROUP BY 1, 3
ORDER BY 1, 2, 4 DESC;

-- Q6. Configured simple lists per tenant (employment types, departments,
--     party types, currencies, copy statuses).
SELECT s."tenantId" AS tenant_id, k.list, v.value, v.ord
FROM settings s
CROSS JOIN LATERAL (VALUES ('employmentTypes'), ('departments'), ('partyTypes'), ('currencies'), ('copyStatuses')) AS k(list)
CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(s.data->'masterData'->k.list, '[]'::jsonb)) WITH ORDINALITY AS v(value, ord)
ORDER BY 1, 2, 4;

-- Q7. Partner (operator) groups: size and whether contact/bank details are
--     filled. Counts only; no detail values are returned.
SELECT s."tenantId"                         AS tenant_id,
       g->>'id'                             AS group_id,
       g->>'name'                           AS group_name,
       g->'companies'                       AS companies,
       g->'platforms'                       AS platforms,
       jsonb_array_length(COALESCE(g->'operators', '[]'::jsonb)) AS operators,
       (SELECT count(*) FROM jsonb_object_keys(COALESCE(g->'operatorDetails', '{}'::jsonb))) AS operators_with_details,
       (SELECT count(*) FROM jsonb_each(COALESCE(g->'operatorDetails', '{}'::jsonb)) e
         WHERE COALESCE(e.value->>'bankAccountNumber', '') <> '') AS with_bank_account
FROM settings s
CROSS JOIN LATERAL jsonb_array_elements(COALESCE(s.data->'masterData'->'operatorGroups', '[]'::jsonb)) g
ORDER BY 1, 3;

-- Q8. Employees: counts per tenant and per department/position only.
--     Also flags the three demo rows (EMP001-EMP003) seeded by the code.
SELECT data->>'tenantId'                  AS tenant_id,
       count(*)                           AS employees,
       count(*) FILTER (WHERE id IN ('EMP001', 'EMP002', 'EMP003')) AS demo_rows,
       count(*) FILTER (WHERE COALESCE(data->>'startDate', '') <> '') AS with_start_date
FROM employees
GROUP BY 1
ORDER BY 1;

SELECT data->>'tenantId' AS tenant_id, data->>'department' AS department, count(*)
FROM employees GROUP BY 1, 2 ORDER BY 1, 3 DESC;

SELECT data->>'tenantId' AS tenant_id, data->>'position' AS position, count(*)
FROM employees GROUP BY 1, 2 ORDER BY 1, 3 DESC;

-- Q9. Vendors: counts only (deferred module), demo rows flagged.
SELECT data->>'tenantId' AS tenant_id,
       count(*)          AS vendors,
       count(*) FILTER (WHERE id IN ('VND001', 'VND002', 'VND003')) AS demo_rows
FROM vendors GROUP BY 1 ORDER BY 1;

-- Q10. DCS documents per department name (Node stores the department name),
--      to compare with masterData.departments and Laravel dcs_departments.
SELECT tenant_id, department_id AS department, count(*)
FROM dcs_documents GROUP BY 1, 2 ORDER BY 1, 3 DESC;

-- Q11. Contract number counters: key shape (tenant|docType|year|...) and
--      the highest value, to see whether renamed doc types left old series.
SELECT id,
       data->>'tenantId'                                        AS tenant_id,
       data->>'seq'                                             AS seq,
       jsonb_array_length(COALESCE(data->'used', '[]'::jsonb))     AS used_count,
       jsonb_array_length(COALESCE(data->'released', '[]'::jsonb)) AS released_count
FROM number_counters
ORDER BY id;

-- Q12. Settings keys actually present per tenant (which optional parts are used).
SELECT "tenantId" AS tenant_id,
       ARRAY(SELECT jsonb_object_keys(data) ORDER BY 1)                                   AS top_keys,
       ARRAY(SELECT jsonb_object_keys(COALESCE(data->'masterData', '{}'::jsonb)) ORDER BY 1) AS master_data_keys
FROM settings
ORDER BY 1;

ROLLBACK;
