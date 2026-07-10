SET DEFINE OFF;

DROP TABLE exhibitions CASCADE CONSTRAINTS;

-- ============================================================
--  Created Exhibitions Table
-- ============================================================
CREATE TABLE exhibitions (
    exhibition_id NUMBER        PRIMARY KEY,
    title         VARCHAR2(255) NOT NULL,
    wing          VARCHAR2(100),
    description   VARCHAR2(1000),
    start_date    DATE,
    end_date      DATE,
    status        VARCHAR2(20)  DEFAULT 'Upcoming'
                  CHECK (status IN ('Active', 'Upcoming', 'Closed')),
    ticket_price  NUMBER(10,2),
    capacity      NUMBER,
    image_url     VARCHAR2(500),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_exhibitions_id
BEFORE INSERT ON exhibitions
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(exhibition_id), 0) + 1 INTO :NEW.exhibition_id FROM exhibitions;
END;
/

-- ============================================================
--  Oracle View: Museum-wide Summary Statistics
--  Used by dashboard.php to demonstrate Oracle Views
-- ============================================================
CREATE OR REPLACE VIEW v_museum_stats AS
SELECT
    (SELECT COUNT(*)                     FROM artifacts)   AS total_artifacts,
    (SELECT COUNT(*)                     FROM gallery)     AS total_gallery,
    (SELECT COUNT(*)                     FROM exhibitions) AS total_exhibitions,
    (SELECT COUNT(*)                     FROM visitors)    AS total_visitors,
    (SELECT NVL(SUM(estimated_value), 0) FROM artifacts)  AS total_artifact_value
FROM dual;

-- ============================================================
--  Sample Exhibition Data
-- ============================================================
INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'The Fall of Rome',
    'Historical Wing',
    'Explore over 200 artifacts from the late Roman Empire, including newly discovered statues and everyday items from the 4th century.',
    DATE '2026-01-15', DATE '2026-12-31', 'Active', 15.00, 500,
    'https://images.unsplash.com/photo-1518998053401-878c73fd616e?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'Modern Expressions',
    'Contemporary Wing',
    'A permanent collection tracking the evolution of modern art from the early 20th century to the present day.',
    DATE '2025-06-01', DATE '2027-12-31', 'Active', 12.00, 300,
    'https://images.unsplash.com/photo-1544640808-32cb4f6864c7?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'Beyond Earth',
    'Science Hall',
    'Discover the history of space exploration. Features actual spacesuits, rover prototypes, and lunar samples on loan from NASA.',
    DATE '2026-09-01', DATE '2027-03-31', 'Upcoming', 18.00, 400,
    'https://images.unsplash.com/photo-1566127444979-b3d2b654e3d7?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'Secrets of Ancient Egypt',
    'Historical Wing',
    'Journey through 3,000 years of Egyptian civilization featuring mummies, hieroglyphic manuscripts, and royal jewelry.',
    DATE '2026-03-01', DATE '2026-08-31', 'Active', 20.00, 600,
    'https://images.unsplash.com/photo-1568797629192-789acf8e4df3?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'The Renaissance Masters',
    'Fine Arts Wing',
    'Celebrating the genius of Leonardo, Michelangelo, and Raphael through rare reproductions and historical documents.',
    DATE '2025-09-01', DATE '2026-02-28', 'Closed', 10.00, 250,
    'https://images.unsplash.com/photo-1578662996442-48f60103fc96?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'Silk Road Treasures',
    'World Cultures Wing',
    'Trace the ancient trade routes connecting China, Persia, and Rome through centuries of remarkable cultural exchange.',
    DATE '2026-07-01', DATE '2026-11-30', 'Active', 14.00, 350,
    'https://images.unsplash.com/photo-1553864250-05b20249ee0e?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'The Industrial Revolution',
    'Science Hall',
    'From steam engines to telegraph machines — explore the inventions that permanently transformed human civilization.',
    DATE '2026-10-15', DATE '2027-05-31', 'Upcoming', 13.00, 450,
    'https://images.unsplash.com/photo-1530968033775-2c92736b131e?auto=format&fit=crop&w=600&q=80'
);

INSERT INTO exhibitions (title, wing, description, start_date, end_date, status, ticket_price, capacity, image_url)
VALUES (
    'Ancient Greece: Birth of Democracy',
    'Historical Wing',
    'From philosophy to politics, explore the foundations of Western civilization through rare ancient Greek artifacts.',
    DATE '2025-01-01', DATE '2025-12-31', 'Closed', 11.00, 400,
    'https://images.unsplash.com/photo-1555924498-36fde60d4fe1?auto=format&fit=crop&w=600&q=80'
);

COMMIT;
