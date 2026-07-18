SET DEFINE OFF;


CREATE OR REPLACE VIEW v_top_exhibition_analytics AS
WITH ticket_stats AS (
    -- CTE 1: aggregate confirmed ticket data per exhibition
    SELECT exhibition_id,
           SUM(total_amount)       AS total_revenue,
           SUM(quantity)           AS total_tickets,
           COUNT(DISTINCT user_id) AS unique_visitors,
           ROUND(AVG(unit_price), 2) AS avg_price
    FROM tickets
    WHERE status = 'Confirmed'
    GROUP BY exhibition_id
),
feedback_stats AS (
    -- CTE 2: aggregate feedback per exhibition
    SELECT exhibition_id,
           COUNT(*)               AS review_count,
           ROUND(AVG(rating), 2)  AS avg_rating,
           MAX(rating)            AS max_rating,
           MIN(rating)            AS min_rating
    FROM feedback
    WHERE status IN ('Reviewed', 'Closed')
    GROUP BY exhibition_id
),
combined AS (
    -- CTE 3: join exhibitions with ticket + feedback CTEs
    SELECT e.exhibition_id, e.title, e.wing, e.status,
           e.ticket_price, e.capacity,
           NVL(ts.total_revenue,   0) AS revenue,
           NVL(ts.total_tickets,   0) AS tickets_sold,
           NVL(ts.unique_visitors, 0) AS unique_visitors,
           NVL(fs.avg_rating,      0) AS avg_rating,
           NVL(fs.review_count,    0) AS review_count,
           ROUND(NVL(ts.total_tickets, 0) / NULLIF(e.capacity, 0) * 100, 1) AS occupancy_pct
    FROM exhibitions e
    LEFT JOIN ticket_stats  ts ON e.exhibition_id = ts.exhibition_id
    LEFT JOIN feedback_stats fs ON e.exhibition_id = fs.exhibition_id
)
SELECT exhibition_id, title, wing, status,
       ticket_price, capacity, revenue,
       tickets_sold, unique_visitors, avg_rating,
       review_count, occupancy_pct,
       RANK() OVER (ORDER BY revenue   DESC) AS revenue_rank,
       RANK() OVER (ORDER BY avg_rating DESC) AS rating_rank
FROM combined;


-- ============================================================
--  2. CONNECT BY LEVEL — Sequence & Hierarchy generation
--  Generate all 12 months of current year for calendar fill
--  Demonstrates: CONNECT BY LEVEL <= N, DUAL, ADD_MONTHS
-- ============================================================
-- Example — 12-month revenue calendar (run in SQL Developer):
-- WITH months AS (
--     SELECT ADD_MONTHS(TRUNC(SYSDATE, 'YEAR'), LEVEL - 1) AS month_start
--     FROM dual
--     CONNECT BY LEVEL <= 12
-- )
-- SELECT TO_CHAR(m.month_start, 'Mon YYYY') AS month_name,
--        NVL(SUM(t.total_amount), 0)         AS revenue,
--        NVL(COUNT(t.ticket_id), 0)          AS tickets
-- FROM months m
-- LEFT JOIN tickets t
--        ON TRUNC(t.booked_at, 'MM') = m.month_start
--       AND t.status = 'Confirmed'
-- GROUP BY m.month_start
-- ORDER BY m.month_start;

-- Generate donation day breakdown (last 30 days):
-- SELECT TRUNC(SYSDATE) - (LEVEL - 1) AS day_date,
--        TO_CHAR(TRUNC(SYSDATE) - (LEVEL - 1), 'Dy DD Mon') AS day_label
-- FROM dual
-- CONNECT BY LEVEL <= 30
-- ORDER BY day_date;


-- ============================================================
--  3. Oracle Native PIVOT — Artifacts condition matrix
--  Demonstrates: PIVOT ... FOR col IN (...)
-- ============================================================
CREATE OR REPLACE VIEW v_artifact_condition_pivot AS
SELECT *
FROM (
    SELECT category,
           NVL(condition_status, 'Unknown') AS cond,
           artifact_id
    FROM artifacts
)
PIVOT (
    COUNT(artifact_id)
    FOR cond IN (
        'Excellent'  AS excellent_count,
        'Good'       AS good_count,
        'Fair'       AS fair_count,
        'Poor'       AS poor_count,
        'Unknown'    AS unknown_count
    )
)
ORDER BY category;


-- ============================================================
--  4. NTILE() — Divide artifacts into value quartiles
--  Demonstrates: NTILE(N) OVER (ORDER BY col)
-- ============================================================
CREATE OR REPLACE VIEW v_artifact_quartiles AS
SELECT artifact_id, name, category, origin_country,
       NVL(estimated_value, 0) AS estimated_value,
       NTILE(4) OVER (ORDER BY NVL(estimated_value, 0) DESC NULLS LAST) AS quartile,
       CASE NTILE(4) OVER (ORDER BY NVL(estimated_value, 0) DESC NULLS LAST)
           WHEN 1 THEN 'Q1 — Top 25%'
           WHEN 2 THEN 'Q2 — Upper Middle'
           WHEN 3 THEN 'Q3 — Lower Middle'
           WHEN 4 THEN 'Q4 — Bottom 25%'
       END AS quartile_label,
       NTILE(4) OVER (PARTITION BY category ORDER BY NVL(estimated_value, 0) DESC NULLS LAST) AS category_quartile
FROM artifacts;


-- ============================================================
--  5. FIRST_VALUE / LAST_VALUE — Window value extraction
--  Most and least valuable artifact per category
--  Demonstrates: FIRST_VALUE, LAST_VALUE with frame clause
-- ============================================================
CREATE OR REPLACE VIEW v_category_extremes AS
SELECT artifact_id, name, category,
       NVL(estimated_value, 0) AS estimated_value,
       FIRST_VALUE(name) OVER (
           PARTITION BY category
           ORDER BY NVL(estimated_value, 0) DESC NULLS LAST
           ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
       ) AS most_valuable_in_category,
       LAST_VALUE(name) OVER (
           PARTITION BY category
           ORDER BY NVL(estimated_value, 0) DESC NULLS LAST
           ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
       ) AS least_valuable_in_category,
       FIRST_VALUE(NVL(estimated_value, 0)) OVER (
           PARTITION BY category
           ORDER BY NVL(estimated_value, 0) DESC NULLS LAST
           ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
       ) AS category_max_value,
       LAST_VALUE(NVL(estimated_value, 0)) OVER (
           PARTITION BY category
           ORDER BY NVL(estimated_value, 0) DESC NULLS LAST
           ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
       ) AS category_min_value
FROM artifacts;


-- ============================================================
--  6. PERCENTILE_CONT / PERCENTILE_DISC
--  Median and quartile values for artifact pricing
--  Demonstrates: ordered-set aggregate functions
-- ============================================================
CREATE OR REPLACE VIEW v_artifact_percentiles AS
SELECT category,
       COUNT(*)                          AS artifact_count,
       ROUND(MIN(estimated_value), 2)    AS min_value,
       ROUND(MAX(estimated_value), 2)    AS max_value,
       ROUND(AVG(estimated_value), 2)    AS avg_value,
       ROUND(PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY estimated_value), 2) AS q1_value,
       ROUND(PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY estimated_value), 2) AS median_value,
       ROUND(PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY estimated_value), 2) AS q3_value,
       ROUND(PERCENTILE_DISC(0.50) WITHIN GROUP (ORDER BY estimated_value), 2) AS median_disc
FROM artifacts
WHERE estimated_value IS NOT NULL
GROUP BY category
ORDER BY category;


-- ============================================================
--  7. CONNECT BY LEVEL for number generation
--  Used for generating ticket seat numbers or month calendars
-- ============================================================
-- Seat number generator (1-50):
-- SELECT LEVEL AS seat_number,
--        CASE WHEN LEVEL <= 10 THEN 'Front'
--             WHEN LEVEL <= 30 THEN 'Middle'
--             ELSE 'Back' END AS section
-- FROM dual CONNECT BY LEVEL <= 50;


-- ============================================================
--  8. Comprehensive WITH CTE for Visit Page
--  Multi-CTE combining exhibitions, artifacts, feedback, donations
-- ============================================================
CREATE OR REPLACE VIEW v_museum_visit_summary AS
WITH active_exhibitions AS (
    SELECT e.exhibition_id, e.title, e.wing, e.ticket_price,
           e.capacity,
           TO_CHAR(e.start_date, 'DD Mon YYYY') AS start_fmt,
           TO_CHAR(e.end_date,   'DD Mon YYYY') AS end_fmt,
           CASE
               WHEN e.start_date > SYSDATE THEN 'Opening in ' || CEIL(e.start_date - SYSDATE) || ' days'
               WHEN e.end_date   < SYSDATE THEN 'Ended'
               ELSE 'Open Now'
           END AS visit_status
    FROM exhibitions e
    WHERE e.status IN ('Active', 'Upcoming')
),
donation_total AS (
    SELECT NVL(SUM(amount), 0) AS total_donated,
           COUNT(*)             AS total_donors
    FROM donations
),
artifact_count AS (
    SELECT COUNT(*)           AS total_artifacts,
           COUNT(DISTINCT category) AS categories
    FROM artifacts
)
SELECT ae.*, dt.total_donated, dt.total_donors,
       ac.total_artifacts, ac.categories
FROM active_exhibitions ae
CROSS JOIN donation_total dt
CROSS JOIN artifact_count ac
ORDER BY ae.ticket_price ASC;


COMMIT;
