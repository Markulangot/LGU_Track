<?php
require_once 'includes/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    ip_address VARCHAR(45) NOT NULL, 
    endpoint VARCHAR(50) NOT NULL, 
    attempts INT DEFAULT 1, 
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    INDEX(ip_address, endpoint)
);");
echo "Table created successfully\n";
?>
