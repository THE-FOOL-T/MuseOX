SET DEFINE OFF;

DROP TABLE feedback  CASCADE CONSTRAINTS;
DROP TABLE donations CASCADE CONSTRAINTS;

CREATE TABLE feedback (
    feedback_id   NUMBER         PRIMARY KEY,
    user_id       NUMBER         REFERENCES users(user_id) ON DELETE SET NULL,
    exhibition_id NUMBER         REFERENCES exhibitions(exhibition_id) ON DELETE CASCADE,
    subject       VARCHAR2(255),
    message       VARCHAR2(2000),
    rating        NUMBER(1)      CHECK (rating BETWEEN 1 AND 5),
    status        VARCHAR2(20)   DEFAULT 'Pending'
                  CHECK (status IN ('Pending', 'Reviewed', 'Closed')),
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE TRIGGER trg_feedback_id
BEFORE INSERT ON feedback
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(feedback_id), 0) + 1 INTO :NEW.feedback_id FROM feedback;
END;
/

CREATE TABLE donations (
    donation_id  NUMBER         PRIMARY KEY,
    user_id      NUMBER         REFERENCES users(user_id) ON DELETE SET NULL,
    donor_name   VARCHAR2(255)  NOT NULL,
    donor_email  VARCHAR2(255),
    amount       NUMBER(12,2)   NOT NULL CHECK (amount > 0),
    purpose      VARCHAR2(100)  DEFAULT 'General Fund'
                 CHECK (purpose IN ('General Fund', 'Artifact Acquisition',
                                    'Exhibition Support', 'Building & Maintenance',
                                    'Education Programs')),
    message      VARCHAR2(1000),
    is_anonymous NUMBER(1)      DEFAULT 0 CHECK (is_anonymous IN (0, 1)),
    donated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE TRIGGER trg_donations_id
BEFORE INSERT ON donations
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(donation_id), 0) + 1 INTO :NEW.donation_id FROM donations;
END;
/

CREATE OR REPLACE PACKAGE pkg_MuseoX AS

    -- Submit visitor feedback for an exhibition
    PROCEDURE sp_SubmitFeedback(
        p_user_id       IN  NUMBER,
        p_exhibition_id IN  NUMBER,
        p_subject       IN  VARCHAR2,
        p_message       IN  VARCHAR2,
        p_rating        IN  NUMBER,
        o_feedback_id   OUT NUMBER
    );

    -- Record a museum donation
    PROCEDURE sp_RecordDonation(
        p_user_id     IN  NUMBER,
        p_name        IN  VARCHAR2,
        p_email       IN  VARCHAR2,
        p_amount      IN  NUMBER,
        p_purpose     IN  VARCHAR2,
        p_message     IN  VARCHAR2,
        p_anonymous   IN  NUMBER,
        o_donation_id OUT NUMBER
    );

    -- Get total amount spent on tickets by a visitor
    FUNCTION fn_GetUserTotalSpent(p_user_id IN NUMBER) RETURN NUMBER;

    -- Get average feedback rating for an exhibition
    FUNCTION fn_GetExhibitionRating(p_exhibition_id IN NUMBER) RETURN NUMBER;

    -- Get total donation amount for a given purpose
    FUNCTION fn_GetDonationByPurpose(p_purpose IN VARCHAR2) RETURN NUMBER;

END pkg_MuseoX;
/

CREATE OR REPLACE PACKAGE BODY pkg_MuseoX AS

    -- --------------------------------------------------------
    PROCEDURE sp_SubmitFeedback(
        p_user_id       IN  NUMBER,
        p_exhibition_id IN  NUMBER,
        p_subject       IN  VARCHAR2,
        p_message       IN  VARCHAR2,
        p_rating        IN  NUMBER,
        o_feedback_id   OUT NUMBER
    ) AS
    BEGIN
        -- Validate rating range
        IF p_rating NOT BETWEEN 1 AND 5 THEN
            RAISE_APPLICATION_ERROR(-20010, 'Rating must be between 1 and 5.');
        END IF;

        INSERT INTO feedback
            (user_id, exhibition_id, subject, message, rating, status)
        VALUES
            (p_user_id, p_exhibition_id, p_subject, p_message, p_rating, 'Pending')
        RETURNING feedback_id INTO o_feedback_id;

        -- Auto-log to audit_logs
        INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
        VALUES (p_user_id, 'FEEDBACK_SUBMITTED', 'FEEDBACK', '0.0.0.0', 'PKG_MUSEOX');

        COMMIT;
    EXCEPTION
        WHEN OTHERS THEN ROLLBACK; RAISE;
    END sp_SubmitFeedback;

    -- --------------------------------------------------------
    PROCEDURE sp_RecordDonation(
        p_user_id     IN  NUMBER,
        p_name        IN  VARCHAR2,
        p_email       IN  VARCHAR2,
        p_amount      IN  NUMBER,
        p_purpose     IN  VARCHAR2,
        p_message     IN  VARCHAR2,
        p_anonymous   IN  NUMBER,
        o_donation_id OUT NUMBER
    ) AS
    BEGIN
        IF p_amount <= 0 THEN
            RAISE_APPLICATION_ERROR(-20020, 'Donation amount must be greater than zero.');
        END IF;

        INSERT INTO donations
            (user_id, donor_name, donor_email, amount, purpose, message, is_anonymous)
        VALUES
            (NULLIF(p_user_id, 0), p_name, p_email, p_amount, p_purpose, p_message, p_anonymous)
        RETURNING donation_id INTO o_donation_id;

        COMMIT;
    EXCEPTION
        WHEN OTHERS THEN ROLLBACK; RAISE;
    END sp_RecordDonation;

    -- --------------------------------------------------------
    FUNCTION fn_GetUserTotalSpent(p_user_id IN NUMBER) RETURN NUMBER AS
        v_total NUMBER := 0;
    BEGIN
        SELECT NVL(SUM(total_amount), 0)
        INTO   v_total
        FROM   tickets
        WHERE  user_id = p_user_id
          AND  status  = 'Confirmed';
        RETURN v_total;
    END fn_GetUserTotalSpent;

    -- --------------------------------------------------------
    FUNCTION fn_GetExhibitionRating(p_exhibition_id IN NUMBER) RETURN NUMBER AS
        v_avg NUMBER := 0;
    BEGIN
        SELECT NVL(ROUND(AVG(rating), 1), 0)
        INTO   v_avg
        FROM   feedback
        WHERE  exhibition_id = p_exhibition_id;
        RETURN v_avg;
    END fn_GetExhibitionRating;

    -- --------------------------------------------------------
    FUNCTION fn_GetDonationByPurpose(p_purpose IN VARCHAR2) RETURN NUMBER AS
        v_total NUMBER := 0;
    BEGIN
        SELECT NVL(SUM(amount), 0)
        INTO   v_total
        FROM   donations
        WHERE  purpose = p_purpose;
        RETURN v_total;
    END fn_GetDonationByPurpose;

END pkg_MuseoX;
/

-- ============================================================
--  Sample Data — Anonymous PL/SQL Block
--  Demonstrates: BEGIN...END anonymous block usage
-- ============================================================
BEGIN
    -- Sample feedback
    INSERT INTO feedback (user_id, exhibition_id, subject, message, rating, status)
    VALUES (NULL, 1, 'Wonderful experience!',
            'The Egyptian wing was beautifully curated. Loved every artifact.',
            5, 'Reviewed');

    INSERT INTO feedback (user_id, exhibition_id, subject, message, rating, status)
    VALUES (NULL, 2, 'Great but crowded',
            'Excellent collection but the hall was very crowded on weekends.',
            4, 'Reviewed');

    INSERT INTO feedback (user_id, exhibition_id, subject, message, rating, status)
    VALUES (NULL, 3, 'Informative',
            'The space exploration section was incredibly detailed and inspiring.',
            5, 'Pending');

    -- Sample donations
    INSERT INTO donations (user_id, donor_name, donor_email, amount, purpose, message, is_anonymous)
    VALUES (NULL, 'Sarah Johnson', 'sarah@example.com', 500.00,
            'Artifact Acquisition', 'Happy to support this amazing museum!', 0);

    INSERT INTO donations (user_id, donor_name, donor_email, amount, purpose, message, is_anonymous)
    VALUES (NULL, 'Anonymous Patron', 'anon@example.com', 1000.00,
            'Exhibition Support', 'Keep up the great exhibitions.', 1);

    INSERT INTO donations (user_id, donor_name, donor_email, amount, purpose, message, is_anonymous)
    VALUES (NULL, 'James Chen', 'james@example.com', 250.00,
            'Education Programs', 'Invest in the next generation!', 0);

    INSERT INTO donations (user_id, donor_name, donor_email, amount, purpose, message, is_anonymous)
    VALUES (NULL, 'Maria Santos', 'maria@example.com', 750.00,
            'General Fund', 'Use wherever needed most.', 0);

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Phase 5 sample data inserted successfully.');
END;
/
