SET DEFINE OFF;

-CREATE MATERIALIZED VIEW mv_artifact_category_stats
BUILD IMMEDIATE
REFRESH COMPLETE ON DEMAND
AS
SELECT category,
       COUNT(*)                        AS artifact_count,
       NVL(ROUND(AVG(estimated_value), 2), 0) AS avg_value,
       NVL(SUM(estimated_value), 0)    AS total_value,
       NVL(MIN(estimated_value), 0)    AS min_value,
       NVL(MAX(estimated_value), 0)    AS max_value,
       COUNT(CASE WHEN condition_status = 'Excellent' THEN 1 END) AS excellent_count,
       COUNT(CASE WHEN condition_status = 'Poor'      THEN 1 END) AS poor_count
FROM artifacts
WHERE category IS NOT NULL
GROUP BY category;


CREATE OR REPLACE SYNONYM mx_artifacts    FOR artifacts;
CREATE OR REPLACE SYNONYM mx_gallery      FOR gallery;
CREATE OR REPLACE SYNONYM mx_exhibitions  FOR exhibitions;
CREATE OR REPLACE SYNONYM mx_tickets      FOR tickets;
CREATE OR REPLACE SYNONYM mx_users        FOR users;
CREATE OR REPLACE SYNONYM mx_feedback     FOR feedback;
CREATE OR REPLACE SYNONYM mx_donations    FOR donations;
CREATE OR REPLACE SYNONYM mx_audit        FOR audit_logs;


CREATE OR REPLACE PROCEDURE sp_GenerateArtifactReport(
    p_category IN VARCHAR2
) AS
    -- Explicit cursor declaration (SELECT statement bound at compile time)
    CURSOR c_artifacts IS
        SELECT artifact_id, name, origin_country,
               condition_status, estimated_value,
               TO_CHAR(acquisition_date, 'DD-MON-YYYY') AS acq_fmt
        FROM   artifacts
        WHERE  UPPER(category) = UPPER(p_category)
        ORDER  BY NVL(estimated_value, 0) DESC;

    -- %ROWTYPE — row variable typed to the cursor's projection
    v_row       c_artifacts%ROWTYPE;

    -- %TYPE — scalar variable typed to a table column
    v_total_val artifacts.estimated_value%TYPE := 0;
    v_count     NUMBER                         := 0;

BEGIN
    DBMS_OUTPUT.PUT_LINE('=============================================');
    DBMS_OUTPUT.PUT_LINE('  ARTIFACT REPORT — Category: ' || UPPER(p_category));
    DBMS_OUTPUT.PUT_LINE('  Generated: ' || TO_CHAR(SYSDATE, 'DD-MON-YYYY HH24:MI:SS'));
    DBMS_OUTPUT.PUT_LINE('=============================================');

    OPEN c_artifacts;         -- open the cursor

    LOOP
        FETCH c_artifacts INTO v_row;   -- fetch each row
        EXIT WHEN c_artifacts%NOTFOUND; -- cursor attribute: no more rows

        v_count     := v_count + 1;
        v_total_val := v_total_val + NVL(v_row.estimated_value, 0);

        DBMS_OUTPUT.PUT_LINE(
            LPAD(v_count, 3) || '. [ID:' || v_row.artifact_id || '] ' ||
            v_row.name ||
            ' | ' || NVL(v_row.origin_country, 'Unknown') ||
            ' | Condition: ' || v_row.condition_status ||
            ' | $' || TO_CHAR(NVL(v_row.estimated_value, 0), 'FM999,999,999.00')
        );
    END LOOP;

    -- %ROWCOUNT: total rows fetched
    DBMS_OUTPUT.PUT_LINE('---------------------------------------------');
    DBMS_OUTPUT.PUT_LINE('Rows fetched   : ' || c_artifacts%ROWCOUNT);
    DBMS_OUTPUT.PUT_LINE('Total value    : $' || TO_CHAR(v_total_val, 'FM999,999,999.00'));
    DBMS_OUTPUT.PUT_LINE(
        'Average value  : $' || TO_CHAR(
            CASE WHEN v_count > 0 THEN ROUND(v_total_val / v_count, 2) ELSE 0 END,
            'FM999,999,999.00')
    );

    CLOSE c_artifacts;        -- close the cursor

EXCEPTION
    WHEN OTHERS THEN
        -- Always close cursor on error
        IF c_artifacts%ISOPEN THEN
            CLOSE c_artifacts;
        END IF;
        DBMS_OUTPUT.PUT_LINE('ERROR: ' || SQLERRM);
        RAISE;
END sp_GenerateArtifactReport;
/

-- Test the procedure (anonymous block)
BEGIN
    DBMS_OUTPUT.ENABLE(1000000);
    sp_GenerateArtifactReport('Sculpture');
END;
/


CREATE OR REPLACE VIEW v_visitor_activity AS
SELECT
    u.user_id,
    u.username,
    u.email,
    u.status,
    r.role_name,
    NVL(v.country, 'Unknown')                AS country,
    NVL(v.phone, '—')                        AS phone,
    NVL(tc.ticket_count, 0)                  AS total_tickets,
    NVL(tc.total_spent, 0)                   AS total_spent,
    NVL(fc.feedback_count, 0)                AS total_feedback,
    NVL(dc.donation_count, 0)                AS total_donations,
    NVL(dc.donation_total, 0)                AS total_donated,
    -- MONTHS_BETWEEN to calculate membership duration
    ROUND(MONTHS_BETWEEN(SYSDATE, CAST(u.created_at AS DATE)), 1) AS months_member
FROM users u
JOIN roles r        ON u.role_id  = r.role_id
LEFT JOIN visitors v ON u.user_id = v.user_id
-- Subquery: ticket stats
LEFT JOIN (
    SELECT user_id,
           COUNT(*)        AS ticket_count,
           SUM(total_amount) AS total_spent
    FROM   tickets
    WHERE  status = 'Confirmed'
    GROUP  BY user_id
) tc ON u.user_id = tc.user_id
-- Subquery: feedback stats
LEFT JOIN (
    SELECT user_id, COUNT(*) AS feedback_count
    FROM   feedback
    GROUP  BY user_id
) fc ON u.user_id = fc.user_id
-- Subquery: donation stats
LEFT JOIN (
    SELECT user_id,
           COUNT(*)    AS donation_count,
           SUM(amount) AS donation_total
    FROM   donations
    WHERE  user_id IS NOT NULL
    GROUP  BY user_id
) dc ON u.user_id = dc.user_id;


COMMIT;
