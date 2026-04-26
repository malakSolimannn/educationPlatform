CREATE TABLE platform_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academy_name VARCHAR(220) NOT NULL,
  email VARCHAR(190),
  phone VARCHAR(50),
  domain_name VARCHAR(190),
  logo_path TEXT,
  banner_path TEXT,
  primary_color VARCHAR(20),
  secondary_color VARCHAR(20),
  about_text TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','assistant') DEFAULT 'assistant',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  status ENUM('active','expired','revoked') DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(180) NOT NULL,
  phone VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(190) UNIQUE,
  password VARCHAR(255) NOT NULL,
  grade_id INT,
  school_name VARCHAR(190),
  wallet_balance DECIMAL(10,2) DEFAULT 0.00,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE student_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  status ENUM('active','expired','revoked') DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NULL,
  item_type ENUM('course','chapter','lesson','package','pass') NOT NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT,

  content_type ENUM('video','pdf','none') DEFAULT 'none',
  video_url TEXT,
  file_path TEXT,

  price DECIMAL(10,2) DEFAULT 0.00,
  duration_days INT NULL,
  access_type ENUM('lifetime','limited') DEFAULT 'lifetime',

  grade_id INT NULL,

  is_free TINYINT(1) DEFAULT 0,
  is_published TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE item_access_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  grants_item_id INT NOT NULL,
  UNIQUE KEY uq_item_grants (item_id, grants_item_id)
);

CREATE TABLE codes (
  id INT AUTO_INCREMENT PRIMARY KEY,

  code VARCHAR(100) NOT NULL UNIQUE,
  code_type ENUM('wallet','item') NOT NULL,

  wallet_amount DECIMAL(10,2) DEFAULT NULL,
  item_id INT DEFAULT NULL,

  is_used TINYINT(1) NOT NULL DEFAULT 0,
  used_by_student_id INT DEFAULT NULL,
  used_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CHECK (
    (code_type = 'wallet' AND wallet_amount IS NOT NULL AND item_id IS NULL)
    OR
    (code_type = 'item' AND wallet_amount IS NULL AND item_id IS NOT NULL)
  )
);

CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,

  student_id INT NOT NULL,
  item_id INT DEFAULT NULL,

  transaction_type ENUM(
    'code_wallet_redeem',
    'code_item_redeem',
    'direct_item_purchase'
  ) NOT NULL,

  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(30) DEFAULT NULL,
  status ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
  notes TEXT DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CHECK (
    (
      transaction_type = 'code_wallet_redeem'
      AND item_id IS NULL
    )
    OR
    (
      transaction_type IN ('code_item_redeem','direct_item_purchase')
      AND item_id IS NOT NULL
    )
  )
);

CREATE TABLE student_access (
  id INT AUTO_INCREMENT PRIMARY KEY,

  student_id INT NOT NULL,
  item_id INT NOT NULL,

  start_date DATETIME NOT NULL,
  end_date DATETIME DEFAULT NULL,
  status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',

  UNIQUE KEY uq_student_access (student_id, item_id)
);

CREATE TABLE lesson_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,

  student_id INT NOT NULL,
  item_id INT NOT NULL,

  is_completed TINYINT(1) DEFAULT 0,
  last_viewed_at DATETIME,

  UNIQUE KEY uq_lesson_progress (student_id, item_id)
);

CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,

  item_id INT DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  time_limit_minutes INT,
  type VARCHAR(200) NOT NULL,
  attempt_limit INT,
  randomize_questions TINYINT(1) DEFAULT 0,
  randomize_answers TINYINT(1) DEFAULT 0,
  is_published TINYINT(1) DEFAULT 1
);

CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,

  quiz_id INT NOT NULL,
  question_type ENUM('mcq','true_false','text') NOT NULL,
  question_text TEXT NOT NULL
);

CREATE TABLE quiz_options (
  id INT AUTO_INCREMENT PRIMARY KEY,

  question_id INT NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) DEFAULT 0
);

CREATE TABLE quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,

  quiz_id INT NOT NULL,
  student_id INT NOT NULL,

  score DECIMAL(8,2),
  started_at DATETIME NOT NULL,
  submitted_at DATETIME
);

CREATE TABLE student_quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,

  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT DEFAULT NULL,
  text_answer TEXT,
  is_correct TINYINT(1),
  grade DECIMAL(5,2)
);

CREATE TABLE assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,

  item_id INT DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  due_date DATETIME NOT NULL
);

CREATE TABLE assignment_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,

  assignment_id INT NOT NULL,
  student_id INT NOT NULL,

  submission_text TEXT,
  file_path TEXT,
  grade DECIMAL(5,2),
  feedback TEXT,
  submitted_at DATETIME,

  UNIQUE KEY uq_assignment_submission (assignment_id, student_id)
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,

  student_id INT NOT NULL,
  title VARCHAR(200),
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,

  title VARCHAR(200),
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;