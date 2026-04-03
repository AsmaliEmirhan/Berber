-- ============================================================
--  Berber Randevu Sistemi - Veritabanı Kurulum Scripti
-- ============================================================

CREATE DATABASE IF NOT EXISTS berber_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE berber_db;

-- ------------------------------------------------------------
-- 1. DISTRICTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS districts (
    id   INT          AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO districts (name) VALUES
    ('Adalar'), ('Arnavutköy'), ('Ataşehir'), ('Avcılar'), ('Bağcılar'),
    ('Bahçelievler'), ('Bakırköy'), ('Başakşehir'), ('Bayrampaşa'), ('Beşiktaş'),
    ('Beykoz'), ('Beylikdüzü'), ('Beyoğlu'), ('Büyükçekmece'), ('Çatalca'),
    ('Çekmeköy'), ('Esenler'), ('Esenyurt'), ('Eyüpsultan'), ('Fatih'),
    ('Gaziosmanpaşa'), ('Güngören'), ('Kadıköy'), ('Kağıthane'), ('Kartal'),
    ('Küçükçekmece'), ('Maltepe'), ('Pendik'), ('Sancaktepe'), ('Sarıyer'),
    ('Silivri'), ('Sultanbeyli'), ('Sultangazi'), ('Şile'), ('Şişli'),
    ('Tuzla'), ('Ümraniye'), ('Üsküdar'), ('Zeytinburnu');

-- ------------------------------------------------------------
-- 2. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(200)  NOT NULL,
    email       VARCHAR(255)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('berber','musteri') NOT NULL,
    is_plus     BOOLEAN       DEFAULT FALSE,
    district_id INT           NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. SHOPS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shops (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT           NOT NULL,
    shop_name   VARCHAR(200)  NOT NULL,
    district_id INT           NULL,
    address     TEXT          NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id)    REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. SHOP_EMPLOYEES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shop_employees (
    id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop_id     INT NOT NULL,
    employee_id INT NOT NULL,
    UNIQUE KEY uq_shop_emp (shop_id, employee_id),
    FOREIGN KEY (shop_id)     REFERENCES shops(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. SERVICES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
    id               INT            AUTO_INCREMENT PRIMARY KEY,
    shop_id          INT            NOT NULL,
    service_name     VARCHAR(200)   NOT NULL,
    price            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    duration_minutes INT            NOT NULL DEFAULT 30,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. APPOINTMENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
    id                INT            AUTO_INCREMENT PRIMARY KEY,
    customer_id       INT            NOT NULL,
    shop_id           INT            NOT NULL,
    employee_id       INT            NOT NULL,
    service_id        INT            NOT NULL,
    appointment_time  DATETIME       NOT NULL,
    status            ENUM('bekliyor','tamamlandi','iptal') DEFAULT 'bekliyor',
    reminder_sent     BOOLEAN        DEFAULT FALSE,
    price_at_that_time DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (customer_id) REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (shop_id)     REFERENCES shops(id)     ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (service_id)  REFERENCES services(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
