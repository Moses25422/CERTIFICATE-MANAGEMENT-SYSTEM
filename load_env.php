<?php

function loadEnvironment(string $filePath): void
{
    if (!file_exists($filePath)) {
        return;
    }

    if (!is_readable($filePath)) {
        throw new RuntimeException('The environment file is not readable: ' . $filePath);
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the environment file: ' . $filePath);
    }

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Invalid environment setting on line ' . ($index + 1) . '.');
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            } elseif ($quote === '"' || $quote === "'") {
                throw new RuntimeException('Unclosed quoted value on line ' . ($index + 1) . '.');
            }
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

loadEnvironment(__DIR__ . '/.env');
