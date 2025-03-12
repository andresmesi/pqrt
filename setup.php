<?php
/**
 * setup.php
 *
 * This setup script configures your open source project by:
 *   1. Collecting configuration details via an HTML form.
 *   2. Writing the configuration to the "db.php" file (used for database and Google OAuth settings).
 *   3. Creating the necessary database schema from "db.sql".
 *   4. Creating two directories ("uploads" and "cache") with write permissions for owner and group.
 *   5. Checking that required dependencies (e.g., Composer vendor directory and FontAwesome) exist.
 *
 * IMPORTANT: Once the setup is complete, it is recommended to remove or restrict access to setup.php.
 *
 * Requirements:
 *   - PHP with PDO extension for MySQL.
 *   - Write permissions in the current directory to create configuration files and folders.
 *   - "db.sql" file must be present with the required SQL statements.
 *   - Composer dependencies installed (i.e. vendor/autoload.php must exist).
 *
 * All configuration is stored in "db.php". Please do not use a file named "config.php".
 *
 * @author  
 * @license MIT
 */

// Enable error reporting for debugging purposes.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------
// Helper function: displayForm()
// ------------------------
/**
 * Displays the HTML configuration form.
 *
 * @param string $error Optional error message to display.
 */
function displayForm($error = '')
{
    // Use htmlspecialchars() to ensure that error messages are safely output.
    $error_html = $error ? '<p style="color:red;">' . htmlspecialchars($error) . '</p>' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="password"], input[type="number"] { width: 300px; padding: 5px; }
        input[type="submit"] { margin-top: 15px; padding: 8px 16px; }
    </style>
</head>
<body>
    <h1>Project Setup</h1>
    {$error_html}
    <p>Please fill in the following configuration details. These values will be saved in <code>db.php</code>.</p>
    <form method="POST" action="setup.php">
        <label for="custom_domain">Custom Domain (e.g., https://yourdomain.com):</label>
        <input type="text" name="custom_domain" id="custom_domain" required>

        <label for="db_host">Database Host:</label>
        <input type="text" name="db_host" id="db_host" value="localhost" required>

        <label for="db_name">Database Name:</label>
        <input type="text" name="db_name" id="db_name" required>

        <label for="db_user">Database User:</label>
        <input type="text" name="db_user" id="db_user" required>

        <label for="db_pass">Database Password:</label>
        <input type="password" name="db_pass" id="db_pass" required>

        <label for="google_client_id">Google Client ID:</label>
        <input type="text" name="google_client_id" id="google_client_id" required>

        <label for="google_client_secret">Google Client Secret:</label>
        <input type="text" name="google_client_secret" id="google_client_secret" required>

        <label for="posts_per_page">Posts Per Page (e.g., 20):</label>
        <input type="number" name="posts_per_page" id="posts_per_page" value="20" min="1" required>

        <label for="use_preload_cache">
            <input type="checkbox" name="use_preload_cache" id="use_preload_cache" value="1" checked>
            Use Preload Cache (true/false)
        </label>

        <input type="submit" value="Save Configuration & Setup">
    </form>
    <hr>
    <p><strong>Note:</strong> Please ensure that the <code>db.sql</code> file (with your SQL schema) exists in the same directory as this setup script.</p>
    <p>Also, the <code>vendor</code> folder (with Composer dependencies) must be present. The setup will check for <code>vendor/autoload.php</code>.</p>
    <p>FontAwesome files should be located in <code>/fontawesome/</code> (if not, please install FontAwesome manually).</p>
</body>
</html>
HTML;
}

// ------------------------
// Main Setup Logic
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Retrieve and sanitize configuration values from the form submission.
    $custom_domain         = filter_input(INPUT_POST, 'custom_domain', FILTER_SANITIZE_STRING);
    $db_host               = filter_input(INPUT_POST, 'db_host', FILTER_SANITIZE_STRING);
    $db_name               = filter_input(INPUT_POST, 'db_name', FILTER_SANITIZE_STRING);
    $db_user               = filter_input(INPUT_POST, 'db_user', FILTER_SANITIZE_STRING);
    $db_pass               = filter_input(INPUT_POST, 'db_pass', FILTER_SANITIZE_STRING);
    $google_client_id      = filter_input(INPUT_POST, 'google_client_id', FILTER_SANITIZE_STRING);
    $google_client_secret  = filter_input(INPUT_POST, 'google_client_secret', FILTER_SANITIZE_STRING);
    $posts_per_page        = filter_input(INPUT_POST, 'posts_per_page', FILTER_VALIDATE_INT);
    $use_preload_cache     = isset($_POST['use_preload_cache']) ? 'true' : 'false';

    // Basic validation: Check that required fields are not empty.
    if (!$custom_domain || !$db_host || !$db_name || !$db_user || !$db_pass || !$google_client_id || !$google_client_secret || !$posts_per_page) {
        displayForm("All fields are required and must be valid.");
        exit;
    }

    // Build the content for the configuration file "db.php"
    // This file will store all configuration settings used by the application.
    $db_php_content = <<<PHP
<?php
/**
 * db.php
 *
 * This file contains configuration settings for the application.
 * It includes database connection details and Google OAuth settings.
 * DO NOT expose this file publicly.
 */

// Custom Domain (optional). If not set, the domain is determined dynamically.
\$custom_domain = '{$custom_domain}';

// Domain used by the application (uses custom domain if provided).
\$domain = \$custom_domain ? \$custom_domain : 'https://' . \$_SERVER['HTTP_HOST'];

// Database configuration.
\$db_host = '{$db_host}';
\$db_name = '{$db_name}';
\$db_user = '{$db_user}';
\$db_pass = '{$db_pass}';

// Google OAuth configuration.
\$google_client_id = '{$google_client_id}';
\$google_client_secret = '{$google_client_secret}';
// Redirect URI is constructed from the domain and the login page.
\$google_redirect_uri = \$domain . '/login.php';

// Application settings.
\$posts_per_page = {$posts_per_page};
\$use_preload_cache = {$use_preload_cache};

try {
    // Initialize PDO connection to the MySQL database.
    \$db = new PDO("mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4", \$db_user, \$db_pass);
    \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$db->exec("SET NAMES 'utf8mb4'");
} catch (PDOException \$e) {
    die("Database connection error: " . \$e->getMessage() . " (Line: " . \$e->getLine() . ")");
}
PHP;

    // Write the configuration to "db.php"
    if (file_put_contents('db.php', $db_php_content) === false) {
        displayForm("Failed to write configuration to db.php. Please check write permissions.");
        exit;
    }

    // Include the newly created configuration file.
    require_once 'db.php';

    // ------------------------
    // Database Schema Setup
    // ------------------------
    // Check if the SQL file exists.
    if (!file_exists('db.sql')) {
        die("Error: The SQL file (db.sql) was not found. Please ensure it exists in the current directory.");
    }

    // Read the contents of db.sql.
    \$sql_contents = file_get_contents('db.sql');
    if (\$sql_contents === false) {
        die("Error: Unable to read db.sql.");
    }

    try {
        // Disable autocommit and start a transaction.
        \$db->beginTransaction();
        // Execute the SQL statements. (Assumes that the SQL file uses semicolon (;) as the delimiter.)
        \$db->exec(\$sql_contents);
        \$db->commit();
    } catch (PDOException \$e) {
        \$db->rollBack();
        die("Error executing SQL: " . \$e->getMessage());
    }

    // ------------------------
    // Create Required Directories
    // ------------------------
    // Define the directories to be created.
    \$directories = ['uploads', 'cache'];

    foreach (\$directories as \$dir) {
        // Check if the directory already exists.
        if (!is_dir(\$dir)) {
            // Attempt to create the directory with permissions 0775 (rwxrwxr-x) recursively.
            if (!mkdir(\$dir, 0775, true)) {
                die("Failed to create directory: " . \$dir);
            } else {
                // Set directory permissions explicitly (in case mkdir() did not set them as expected).
                chmod(\$dir, 0775);
            }
        }
    }

    // ------------------------
    // Check for Composer Dependencies & FontAwesome
    // ------------------------
    // Verify that the vendor directory exists (required for Google login via Composer).
    if (!file_exists('vendor/autoload.php')) {
        echo "<p style='color:red;'>Warning: Composer dependencies not found. Please run <code>composer install</code> to install required packages.</p>";
    }

    // Verify that the FontAwesome directory exists.
    if (!is_dir('fontawesome')) {
        echo "<p style='color:red;'>Warning: FontAwesome directory not found. Please download and install FontAwesome in the <code>fontawesome</code> folder.</p>";
    }

    // ------------------------
    // Setup Completed Successfully
    // ------------------------
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Complete</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        p { font-size: 16px; }
    </style>
</head>
<body>
    <h1>Setup Completed Successfully</h1>
    <p>Your configuration has been saved to <code>db.php</code>.</p>
    <p>The database schema has been created using <code>db.sql</code>.</p>
    <p>The required directories (<code>uploads</code> and <code>cache</code>) have been created with write permissions for the owner and group.</p>
    <p>Please verify that the <code>vendor</code> directory and the <code>fontawesome</code> folder are present. If not, please install them manually.</p>
    <p><strong>Important:</strong> For security reasons, remove or restrict access to this <code>setup.php</code> file once the installation is complete.</p>
</body>
</html>
HTML;

} else {
    // Display the configuration form when the request method is GET.
    displayForm();
}
?>