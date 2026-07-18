SET DEFINE OFF;

-- ============================================================
--  Phase 7: Analytics SQL — Window Functions & Advanced Queries
--  Demonstrates: RANK(), DENSE_RANK(), ROW_NUMBER(), LAG(),
--                LEAD(), LISTAGG, PARTITION BY, INTERVAL
-- ============================================================


-- ============================================================
--  View 1: v_artifact_value_rank
--  Rank artifacts by estimated_value within each category
--  Demonstrates: RANK() OVER (PARTITION BY ... ORDER BY ...)
-- ============================================================
CREATE OR REPLACE VIEW v_artifact_value_rank AS
SELECT
    artifact_id,
    name,
    category,
    origin_country,
    condition_status,
    NVL(estimated_value, 0) AS estimated_value,
    RANK()       OVER (PARTITION BY category ORDER BY NVL(estimated_value, 0) DESC) AS value_rank,
    DENSE_RANK() OVER (PARTITION BY category ORDER BY NVL(estimated_value, 0) DESC) AS dense_rank,
    ROW_NUMBER() OVER (PARTITION BY category ORDER BY NVL(estimated_value, 0) DESC) AS row_num,
    ROUND(
        NVL(estimated_value, 0) /
        NULLIF(SUM(NVL(estimated_value, 0)) OVER (PARTITION BY category), 0) * 100,
        2
    ) AS pct_of_category_total
FROM artifacts;


-- ============================================================
--  View 2: v_exhibition_revenue_rank
--  Rank exhibitions by confirmed ticket revenue
--  Demonstrates: DENSE_RANK() OVER (ORDER BY agg DESC)
-- ============================================================
CREATE OR REPLACE VIEW v_exhibition_revenue_rank AS
SELECT
    e.exhibition_id,
    e.title,
    e.wing,
    e.status,
    NVL(t.total_revenue, 0)     AS revenue,
    NVL(t.total_tickets, 0)     AS tickets_sold,
    NVL(t.unique_visitors, 0)   AS unique_visitors,
    DENSE_RANK() OVER (ORDER BY NVL(t.total_revenue, 0) DESC) AS revenue_rank,
    CASE
        WHEN NVL(t.total_revenue, 0) > 10000 THEN 'Platinum'
        WHEN NVL(t.total_revenue, 0) > 5000  THEN 'Gold'
        WHEN NVL(t.total_revenue, 0) > 1000  THEN 'Silver'
        ELSE 'Standard'
    END AS revenue_tier
FROM exhibitions e
LEFT JOIN (
    SELECT exhibition_id,
           SUM(total_amount)    AS total_revenue,
           SUM(quantity)        AS total_tickets,
           COUNT(DISTINCT user_id) AS unique_visitors
    FROM   tickets
    WHERE  status = 'Confirmed'
    GROUP  BY exhibition_id
) t ON e.exhibition_id = t.exhibition_id;


-- ============================================================
--  View 3: v_monthly_ticket_trends
--  Monthly ticket revenue with MoM comparison
--  Demonstrates: LAG() OVER (ORDER BY ...) analytic function
--                LEAD() for forward-looking comparison
-- ============================================================
CREATE OR REPLACE VIEW v_monthly_ticket_trends AS
SELECT
    month_label,
    monthly_revenue,
    ticket_count,
    LAG(monthly_revenue) OVER (ORDER BY month_label)  AS prev_month_revenue,
    LEAD(monthly_revenue) OVER (ORDER BY month_label) AS next_month_revenue,
    monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY month_label) AS mom_change,
    ROUND(
        (monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY month_label)) /
        NULLIF(LAG(monthly_revenue) OVER (ORDER BY month_label), 0) * 100,
        1
    ) AS mom_pct_change
FROM (
    SELECT TO_CHAR(TRUNC(booked_at, 'MM'), 'YYYY-MM') AS month_label,
           SUM(total_amount)  AS monthly_revenue,
           COUNT(ticket_id)   AS ticket_count
    FROM   tickets
    WHERE  status = 'Confirmed'
    GROUP  BY TRUNC(booked_at, 'MM')
);


-- ============================================================
--  View 4: v_visitor_ticket_history
--  Each visitor's tickets ranked by booking date
--  Demonstrates: ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY booked_at DESC)
-- ============================================================
CREATE OR REPLACE VIEW v_visitor_ticket_history AS
SELECT
    t.ticket_id,
    t.user_id,
    u.username,
    e.title         AS exhibition_title,
    t.ticket_type,
    t.quantity,
    t.unit_price,
    t.total_amount,
    t.status,
    t.booked_at,
    ROW_NUMBER() OVER (PARTITION BY t.user_id ORDER BY t.booked_at DESC) AS booking_seq
FROM tickets t
JOIN users u       ON t.user_id       = u.user_id
JOIN exhibitions e ON t.exhibition_id = e.exhibition_id;






COMMIT;
