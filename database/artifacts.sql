SET DEFINE OFF;

DROP TABLE artifacts CASCADE CONSTRAINTS;


CREATE TABLE artifacts (
    artifact_id       NUMBER        PRIMARY KEY,
    name              VARCHAR2(255) NOT NULL,
    category          VARCHAR2(100),
    origin_country    VARCHAR2(100),
    historical_period VARCHAR2(100),
    estimated_value   NUMBER,
    condition_status  VARCHAR2(50)  DEFAULT 'Good'
                      CHECK (condition_status IN ('Excellent', 'Good', 'Fair', 'Poor')),
    short_description VARCHAR2(1000),
    image_url         VARCHAR2(255),
    wikipedia_url     VARCHAR2(255),
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_artifacts_id
BEFORE INSERT ON artifacts
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(artifact_id), 0) + 1 INTO :NEW.artifact_id FROM artifacts;
END;
/

-- Inserted Realistic Sample Data
INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Rosetta Stone', 
    'Manuscript', 
    'Egypt', 
    'Ancient', 
    50000000, 
    'Excellent',
    'A granodiorite stele inscribed with three versions of a decree issued in Memphis, Egypt in 196 BC.', 
    '../assets/images/artifacts/rosetta_stone.jpg', 
    'https://en.wikipedia.org/wiki/Rosetta_Stone'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Bust of Nefertiti', 
    'Sculpture', 
    'Egypt', 
    'Ancient', 
    400000000, 
    'Excellent',
    'A painted stucco-coated limestone bust of Nefertiti, the Great Royal Wife of the Egyptian pharaoh Akhenaten.', 
    '../assets/images/artifacts/nefertiti.jpg', 
    'https://en.wikipedia.org/wiki/Nefertiti_Bust'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Terracotta Warrior', 
    'Sculpture', 
    'China', 
    'Ancient', 
    4500000, 
    'Good',
    'A collection of terracotta sculptures depicting the armies of Qin Shi Huang, the first Emperor of China.', 
    '../assets/images/artifacts/terracotta.jpg', 
    'https://en.wikipedia.org/wiki/Terracotta_Army'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Venus de Milo', 
    'Sculpture', 
    'Greece', 
    'Ancient', 
    1000000000, 
    'Fair',
    'An ancient Greek sculpture from the Hellenistic period, depicting Aphrodite.', 
    '../assets/images/artifacts/venus.jpg', 
    'https://en.wikipedia.org/wiki/Venus_de_Milo'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Sutton Hoo Helmet', 
    'Jewelry', 
    'United Kingdom', 
    'Medieval', 
    3000000, 
    'Good',
    'An ornate Anglo-Saxon helmet found during an excavation in 1939.', 
    '../assets/images/artifacts/sutton_hoo.jpg', 
    'https://en.wikipedia.org/wiki/Sutton_Hoo_helmet'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Gutenberg Bible', 
    'Manuscript', 
    'Germany', 
    'Renaissance', 
    35000000, 
    'Fair',
    'The first major book printed using mass-produced movable metal type in Europe.', 
    '../assets/images/artifacts/gutenberg.jpg', 
    'https://en.wikipedia.org/wiki/Gutenberg_Bible'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Ming Dynasty Vase', 
    'Pottery', 
    'China', 
    'Renaissance', 
    22000000, 
    'Excellent',
    'Exquisite blue and white porcelain vase from the Ming Dynasty period.', 
    '../assets/images/artifacts/ming_vase.jpg', 
    'https://en.wikipedia.org/wiki/Ming_dynasty_ceramics'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Antikythera Mechanism', 
    'Scientific Instrument', 
    'Greece', 
    'Ancient', 
    15000000, 
    'Poor',
    'An ancient Greek hand-powered orrery, described as the oldest known example of an analogue computer.', 
    '../assets/images/artifacts/antikythera.jpg', 
    'https://en.wikipedia.org/wiki/Antikythera_mechanism'
);

INSERT INTO artifacts (name, category, origin_country, historical_period, estimated_value, condition_status, short_description, image_url, wikipedia_url) 
VALUES (
    'Hope Diamond', 
    'Jewelry', 
    'India', 
    'Modern', 
    250000000, 
    'Excellent',
    'One of the most famous jewels in the world, with ownership records dating back almost four centuries.', 
    '../assets/images/artifacts/hope_diamond.jpg', 
    'https://en.wikipedia.org/wiki/Hope_Diamond'
);

COMMIT;
