CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(30) NOT NULL,
    email VARCHAR(254) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

SET @email_column_migration = IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'email') = 0,
    'ALTER TABLE users ADD COLUMN email VARCHAR(254) NULL AFTER username',
    'SELECT 1'
);
PREPARE email_migration FROM @email_column_migration;
EXECUTE email_migration;
DEALLOCATE PREPARE email_migration;

UPDATE users
SET email = NULL
WHERE email IS NOT NULL AND TRIM(email) = '';

UPDATE users
SET email = LOWER(TRIM(email))
WHERE email IS NOT NULL;

SET @users_email_unique_migration = IF(
    (SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND INDEX_NAME = 'uq_users_email') = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)',
    'SELECT 1'
);
PREPARE users_email_unique_migration FROM @users_email_unique_migration;
EXECUTE users_email_unique_migration;
DEALLOCATE PREPARE users_email_unique_migration;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token_hash (token_hash),
    KEY idx_password_resets_user_active (user_id, used_at, expires_at),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE password_resets
    MODIFY token_hash VARCHAR(255) NOT NULL;

SET @attempts_column_migration = IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'password_resets'
       AND COLUMN_NAME = 'attempts') = 0,
    'ALTER TABLE password_resets ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER token_hash',
    'SELECT 1'
);
PREPARE attempts_migration FROM @attempts_column_migration;
EXECUTE attempts_migration;
DEALLOCATE PREPARE attempts_migration;

CREATE TABLE IF NOT EXISTS confessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author VARCHAR(30) NOT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    tag VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

SET @confession_anonymous_column_migration = IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'confessions'
       AND COLUMN_NAME = 'is_anonymous') = 0,
    'ALTER TABLE confessions ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER author',
    'SELECT 1'
);
PREPARE confession_anonymous_migration FROM @confession_anonymous_column_migration;
EXECUTE confession_anonymous_migration;
DEALLOCATE PREPARE confession_anonymous_migration;
