SET DEFINE OFF;


--  Phase 4: Performance Indexes
--  Demonstrates: CREATE INDEX, composite indexes, function-based index


-- artifacts indexes
CREATE INDEX idx_artifacts_category    ON artifacts (category);
CREATE INDEX idx_artifacts_country     ON artifacts (origin_country);
CREATE INDEX idx_artifacts_condition   ON artifacts (condition_status);
CREATE INDEX idx_artifacts_value       ON artifacts (estimated_value DESC);

-- gallery indexes
CREATE INDEX idx_gallery_artist        ON gallery (artist_name);
CREATE INDEX idx_gallery_category      ON gallery (category);
CREATE INDEX idx_gallery_year          ON gallery (creation_year);

-- exhibitions indexes
CREATE INDEX idx_exhibitions_status    ON exhibitions (status);
CREATE INDEX idx_exhibitions_dates     ON exhibitions (start_date, end_date);
CREATE INDEX idx_exhibitions_wing      ON exhibitions (wing);

-- tickets indexes (composite for JOIN performance)
CREATE INDEX idx_tickets_user          ON tickets (user_id);
CREATE INDEX idx_tickets_exhibition    ON tickets (exhibition_id, status);
CREATE INDEX idx_tickets_booked        ON tickets (booked_at DESC);

-- audit_logs indexes
CREATE INDEX idx_audit_user_time       ON audit_logs (user_id, log_timestamp DESC);
CREATE INDEX idx_audit_action          ON audit_logs (action_performed);

-- users indexes
CREATE INDEX idx_users_role            ON users (role_id);
CREATE INDEX idx_users_status          ON users (status);

-- Function-based index for case-insensitive search on artifact names
CREATE INDEX idx_artifacts_name_upper  ON artifacts (UPPER(name));
CREATE INDEX idx_gallery_name_upper    ON gallery   (UPPER(artwork_name));



--  Oracle Stored Function: fn_GetArtifactCount
--  Returns count of artifacts in a given category
--  Demonstrates: FUNCTION, IN param, RETURN, SELECT INTO

CREATE OR REPLACE FUNCTION fn_GetArtifactCount(p_category IN VARCHAR2)
RETURN NUMBER AS
    v_count NUMBER := 0;
BEGIN
    SELECT COUNT(*)
    INTO   v_count
    FROM   artifacts
    WHERE  UPPER(category) = UPPER(p_category);

    RETURN v_count;
END fn_GetArtifactCount;
/


--  Oracle Stored Function: fn_GetTicketRevenue
--  Returns total confirmed revenue for an exhibition

CREATE OR REPLACE FUNCTION fn_GetTicketRevenue(p_exhibition_id IN NUMBER)
RETURN NUMBER AS
    v_revenue NUMBER := 0;
BEGIN
    SELECT NVL(SUM(total_amount), 0)
    INTO   v_revenue
    FROM   tickets
    WHERE  exhibition_id = p_exhibition_id
      AND  status = 'Confirmed';

    RETURN v_revenue;
END fn_GetTicketRevenue;
/


--  Updated Oracle View: v_exhibition_summary
--  Joins exhibitions with ticket revenue (using function)

CREATE OR REPLACE VIEW v_exhibition_summary AS
SELECT
    e.exhibition_id,
    e.title,
    e.wing,
    e.status,
    e.ticket_price,
    e.capacity,
    NVL(t.tickets_sold, 0)          AS tickets_sold,
    NVL(t.total_revenue, 0)         AS total_revenue,
    e.capacity - NVL(t.tickets_sold, 0) AS remaining_capacity,
    CASE
        WHEN e.capacity = 0 THEN 0
        ELSE ROUND(NVL(t.tickets_sold, 0) * 100 / e.capacity, 1)
    END AS fill_percentage
FROM exhibitions e
LEFT JOIN (
    SELECT exhibition_id,
           SUM(quantity)     AS tickets_sold,
           SUM(total_amount) AS total_revenue
    FROM   tickets
    WHERE  status = 'Confirmed'
    GROUP  BY exhibition_id
) t ON e.exhibition_id = t.exhibition_id;


COMMIT;
