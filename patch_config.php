<?php
$file_path = '/Users/test1/Desktop/DemoProjects/Moodle/moodle-customize-react/moode-API-server/config.php';
$content = file_get_contents($file_path);

$target = <<<EOT
unset(\$CFG);  // Ignore this line
global \$CFG;  // This is necessary here for PHPUnit execution
\$CFG = new stdClass();
EOT;

$replacement = <<<EOT
unset(\$CFG);  // Ignore this line
global \$CFG;  // This is necessary here for PHPUnit execution
\$CFG = new stdClass();

// Load environment variables from .env file
\$env_path = __DIR__ . '/.env';
if (file_exists(\$env_path)) {
    \$lines = file(\$env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (\$lines as \$line) {
        if (strpos(trim(\$line), '#') === 0) continue;
        if (strpos(\$line, '=') !== false) {
            list(\$name, \$value) = explode('=', \$line, 2);
            \$name = trim(\$name);
            \$value = trim(\$value);
            putenv("\$name=\$value");
            \$_ENV[\$name] = \$value;
        }
    }
}
EOT;

$new_content = str_replace($target, $replacement, $content);
file_put_contents($file_path, $new_content);
echo "Replaced successfully.";
