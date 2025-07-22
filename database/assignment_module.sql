-- Assignment metadata table
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assignment scores table
CREATE TABLE IF NOT EXISTS assignment_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    roll_no VARCHAR(20) NOT NULL,
    status TINYINT(1) DEFAULT 0, -- 1 = submitted, 0 = not submitted
    score INT DEFAULT 0,
    section_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 