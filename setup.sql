CREATE DATABASE IF NOT EXISTS argonar_construction;
USE argonar_construction;

CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game VARCHAR(50) NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    team_logo VARCHAR(255) DEFAULT '',
    member_1 VARCHAR(100) NOT NULL,
    member_2 VARCHAR(100) NOT NULL,
    member_3 VARCHAR(100) NOT NULL,
    member_4 VARCHAR(100) NOT NULL,
    member_5 VARCHAR(100) NOT NULL,
    substitute VARCHAR(100) DEFAULT '',
    ref_code VARCHAR(20) DEFAULT NULL,
    payment_proof VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (ref_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solo_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game VARCHAR(50) NOT NULL,
    real_name VARCHAR(100) DEFAULT '',
    player_name VARCHAR(100) NOT NULL,
    rank_tier VARCHAR(50) NOT NULL,
    preferred_role VARCHAR(50) DEFAULT '',
    profile_photo VARCHAR(255) DEFAULT '',
    ref_code VARCHAR(20) DEFAULT NULL,
    payment_proof VARCHAR(255) NOT NULL,
    status ENUM('pending', 'matched', 'approved') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (ref_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game VARCHAR(50) NOT NULL,
    bracket_side ENUM('winners', 'losers', 'grand') DEFAULT 'winners',
    round INT NOT NULL,
    match_order INT NOT NULL,
    team1_name VARCHAR(100) NOT NULL,
    team2_name VARCHAR(100) NOT NULL,
    team1_score INT NOT NULL DEFAULT 0,
    team2_score INT NOT NULL DEFAULT 0,
    winner VARCHAR(100) NOT NULL DEFAULT '',
    status ENUM('pending', 'upcoming', 'live', 'completed') NOT NULL DEFAULT 'pending',
    scheduled_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_code VARCHAR(20) DEFAULT '',
    player_name VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'reviewed', 'closed') DEFAULT 'open',
    admin_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT DEFAULT NULL COMMENT 'NULL = broadcast to all',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'bi-bell',
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY (account_id),
    KEY (is_read),
    KEY (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game VARCHAR(50) NOT NULL,
    season VARCHAR(50) NOT NULL DEFAULT 'Season 1',
    placement INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    prize VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
