-- ==============================================================================
-- MySQL Initialization Script
-- ==============================================================================
-- Purpose: Create database, set proper privileges, and configure timezone
-- Runs once on first container startup
-- ==============================================================================

-- Create database with UTF8MB4 charset (required for full Unicode support)
CREATE DATABASE IF NOT EXISTS `ci_insights`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Grant privileges to application user
GRANT ALL PRIVILEGES ON `ci_insights`.* TO 'ci_user'@'%';

-- Create test database for automated testing
CREATE DATABASE IF NOT EXISTS `ci_insights_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `ci_insights_test`.* TO 'ci_user'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;