SET DEFINE OFF;

DROP TABLE gallery CASCADE CONSTRAINTS;


CREATE TABLE gallery (
    gallery_id     NUMBER        PRIMARY KEY,
    artwork_name   VARCHAR2(255) NOT NULL,
    artist_name    VARCHAR2(255),
    category       VARCHAR2(100),
    origin_country VARCHAR2(100),
    creation_year  NUMBER,
    description    VARCHAR2(1000),
    image_url      VARCHAR2(255),
    reference_url  VARCHAR2(255),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_gallery_id
BEFORE INSERT ON gallery
FOR EACH ROW
BEGIN
    SELECT NVL(MAX(gallery_id), 0) + 1 INTO :NEW.gallery_id FROM gallery;
END;
/

-- Inserted Sample Data
INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'Mona Lisa', 
    'Leonardo da Vinci', 
    'Renaissance Art', 
    'Italy', 
    1503, 
    'A half-length portrait painting by Italian artist Leonardo da Vinci.', 
    '../assets/images/gallery/mona_lisa.jpg', 
    'https://en.wikipedia.org/wiki/Mona_Lisa'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'Starry Night', 
    'Vincent van Gogh', 
    'Modern Art', 
    'Netherlands', 
    1889, 
    'An oil-on-canvas painting by the Dutch Post-Impressionist painter Vincent van Gogh.', 
    '../assets/images/gallery/starry_night.jpg', 
    'https://en.wikipedia.org/wiki/The_Starry_Night'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'The Last Supper', 
    'Leonardo da Vinci', 
    'Renaissance Art', 
    'Italy', 
    1498, 
    'A late 15th-century mural painting by Leonardo da Vinci housed by the refectory of the Convent of Santa Maria delle Grazie in Milan.', 
    '../assets/images/gallery/last_supper.jpg', 
    'https://en.wikipedia.org/wiki/The_Last_Supper_(Leonardo)'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'The Scream', 
    'Edvard Munch', 
    'Modern Art', 
    'Norway', 
    1893, 
    'The popular name given to a composition created by Norwegian Expressionist artist Edvard Munch.', 
    '../assets/images/gallery/scream.jpg', 
    'https://en.wikipedia.org/wiki/The_Scream'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'Girl with a Pearl Earring', 
    'Johannes Vermeer', 
    'Historical Painting', 
    'Netherlands', 
    1665, 
    'An oil painting by Dutch Golden Age painter Johannes Vermeer.', 
    '../assets/images/gallery/pearl_earring.jpg', 
    'https://en.wikipedia.org/wiki/Girl_with_a_Pearl_Earring'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'David', 
    'Michelangelo', 
    'Sculpture', 
    'Italy', 
    1504, 
    'A masterpiece of Renaissance sculpture, created in marble by the Italian artist Michelangelo.', 
    '../assets/images/gallery/david.jpg', 
    'https://en.wikipedia.org/wiki/David_(Michelangelo)'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'The Night Watch', 
    'Rembrandt', 
    'Historical Painting', 
    'Netherlands', 
    1642, 
    'A famous painting by Rembrandt van Rijn, depicting a city guard moving out.', 
    '../assets/images/gallery/night_watch.jpg', 
    'https://en.wikipedia.org/wiki/The_Night_Watch'
);

INSERT INTO gallery (artwork_name, artist_name, category, origin_country, creation_year, description, image_url, reference_url) 
VALUES (
    'The Persistence of Memory', 
    'Salvador Dali', 
    'Surrealism', 
    'Spain', 
    1931, 
    'A famous surrealist painting featuring melting clocks in a dreamscape.', 
    '../assets/images/gallery/persistence.jpg', 
    'https://en.wikipedia.org/wiki/The_Persistence_of_Memory'
);

COMMIT;
