SET DEFINE OFF;

DROP TABLE tickets CASCADE CONSTRAINTS;


CREATE TABLE tickets (
    ticket_id      NUMBER          PRIMARY KEY,
    user_id        NUMBER          NOT NULL REFERENCES users(user_id)       ON DELETE CASCADE,
    exhibition_id  NUMBER          NOT NULL REFERENCES exhibitions(exhibition_id) ON DELETE CASCADE,
    ticket_type    VARCHAR2(20)    DEFAULT 'Adult'
                   CHECK (ticket_type IN ('Adult', 'Child', 'Senior')),
    quantity       NUMBER          DEFAULT 1  CHECK (quantity BETWEEN 1 AND 10),
    unit_price     NUMBER(10,2)    NOT NULL,
    total_amount   NUMBER(10,2)    NOT NULL,
    status         VARCHAR2(20)    DEFAULT 'Confirmed'
                   CHECK (status IN ('Confirmed', 'Cancelled')),
    booked_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
);

-- Trigger: Auto-Increment ticket_id
CREATE OR REPLACE TRIGGER trg_tickets_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(ticket_id), 0) + 1 INTO :NEW.ticket_id FROM tickets;
END;
/

-- Trigger: Automatically log booking to audit_logs
CREATE OR REPLACE TRIGGER trg_ticket_booking_audit
AFTER INSERT ON tickets
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
    VALUES (:NEW.user_id, 'TICKET_BOOKED', 'TICKETS', '0.0.0.0', 'SYSTEM_TRIGGER');
END;
/

-- Trigger: Log ticket cancellation
CREATE OR REPLACE TRIGGER trg_ticket_cancel_audit
AFTER UPDATE OF status ON tickets
FOR EACH ROW
WHEN (NEW.status = 'Cancelled')
BEGIN
    INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
    VALUES (:NEW.user_id, 'TICKET_CANCELLED', 'TICKETS', '0.0.0.0', 'SYSTEM_TRIGGER');
END;
/


--  sp_BookTicket: PL/SQL Stored Procedure for Ticket Booking


CREATE OR REPLACE PROCEDURE sp_BookTicket(
    p_user_id       IN  NUMBER,
    p_exhibition_id IN  NUMBER,
    p_ticket_type   IN  VARCHAR2,
    p_quantity      IN  NUMBER,
    o_ticket_id     OUT NUMBER,
    o_total_amount  OUT NUMBER
) AS
    v_price      NUMBER(10,2);
    v_capacity   NUMBER;
    v_booked     NUMBER;
    v_multiplier NUMBER := 1.0;
BEGIN
    -- Fetch exhibition ticket price and capacity
    SELECT ticket_price, capacity
    INTO v_price, v_capacity
    FROM exhibitions
    WHERE exhibition_id = p_exhibition_id
      AND status IN ('Active', 'Upcoming');

    -- Check current confirmed bookings against capacity
    SELECT NVL(SUM(quantity), 0)
    INTO v_booked
    FROM tickets
    WHERE exhibition_id = p_exhibition_id
      AND status = 'Confirmed';

    IF (v_booked + p_quantity) > v_capacity THEN
        RAISE_APPLICATION_ERROR(-20001, 'Not enough capacity. Only ' ||
            (v_capacity - v_booked) || ' spots remaining.');
    END IF;

    -- Apply ticket type discount multiplier
    IF p_ticket_type = 'Child' THEN
        v_multiplier := 0.5;
    ELSIF p_ticket_type = 'Senior' THEN
        v_multiplier := 0.8;
    END IF;

    -- Calculate final unit price and total
    v_price        := ROUND(v_price * v_multiplier, 2);
    o_total_amount := v_price * p_quantity;

    -- Insert confirmed ticket
    INSERT INTO tickets (user_id, exhibition_id, ticket_type, quantity, unit_price, total_amount, status)
    VALUES (p_user_id, p_exhibition_id, p_ticket_type, p_quantity, v_price, o_total_amount, 'Confirmed')
    RETURNING ticket_id INTO o_ticket_id;

    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20002, 'Exhibition not found or is closed.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


--  Updated Oracle View: v_museum_stats (adds ticket stats)

CREATE OR REPLACE VIEW v_museum_stats AS
SELECT
    (SELECT COUNT(*)                     FROM artifacts)                                AS total_artifacts,
    (SELECT COUNT(*)                     FROM gallery)                                  AS total_gallery,
    (SELECT COUNT(*)                     FROM exhibitions)                              AS total_exhibitions,
    (SELECT COUNT(*)                     FROM visitors)                                 AS total_visitors,
    (SELECT NVL(SUM(estimated_value), 0) FROM artifacts)                               AS total_artifact_value,
    (SELECT COUNT(*)                     FROM tickets WHERE status = 'Confirmed')       AS total_tickets,
    (SELECT NVL(SUM(total_amount), 0)    FROM tickets WHERE status = 'Confirmed')      AS total_revenue
FROM dual;

COMMIT;
