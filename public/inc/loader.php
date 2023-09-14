<?php
# Mattermost User Merge Tool

# Copyright (c) 2023 Maxwell Power
#
# Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without
# restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom
# the Software is furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
# AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
# ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

session_start();
ob_start();
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);
ini_set('memory_limit', -1);

// Start Logging
if (!isset($openlog)) {
    $openLog = openlog("MM-MERGE", LOG_ODELAY, LOG_LOCAL0);
}

// Database configuration
$DB_HOST = getenv('DB_HOST');
$DB_PORT = getenv('DB_PORT') ?: 5432;
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASSWORD = getenv('DB_PASSWORD');

// Other configuration
$safeMode = getenv('SAFE_MODE');
if ($safeMode) {
    syslog(LOG_INFO, "Safe mode enabled");
    $_SESSION['safe_mode'] = true;
} else {
    $_SESSION['safe_mode'] = false;
}

$debugUsers = getenv('DEBUG_USERS');
if ($debugUsers) {
    syslog(LOG_INFO, "Debug users enabled");
    $_SESSION['debug_users'] = true;
} else {
    $_SESSION['debug_users'] = false;
}

if (!isset($pdo)) {
    syslog(LOG_INFO, "Connecting to PostgresSQL database");
    // Create a PDO connection to the PostgresSQL database
    try {
        $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;user=$DB_USER;password=$DB_PASSWORD";
        $pdo = new PDO($dsn);
        syslog(LOG_INFO, "Connected to database");
    } catch (PDOException $e) {
        syslog(LOG_EMERG, "Connection failed: " . $e->getMessage());
        die("Connection failed: " . $e->getMessage());
    }
}
