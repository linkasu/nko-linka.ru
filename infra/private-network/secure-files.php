<?php

function linka_secure_regular_file(string $path, bool $private): bool
{
    $metadata = @lstat($path);
    if ($metadata === false || is_link($path) || !is_file($path)) {
        return false;
    }

    return !$private || (($metadata['mode'] & 0077) === 0);
}

function linka_secure_random_output_directory(string $parent, string $prefix): ?string
{
    $metadata = @lstat($parent);
    $real_parent = realpath($parent);
    if ($metadata === false || $real_parent === false || is_link($parent) || !is_dir($real_parent) || !is_writable($real_parent)) {
        return null;
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $suffix = bin2hex(random_bytes(16));
        } catch (Throwable $error) {
            return null;
        }
        $directory = $real_parent . '/' . $prefix . '-' . $suffix;
        if (@mkdir($directory, 0700)) {
            chmod($directory, 0700);
            return $directory;
        }
    }

    return null;
}

function linka_secure_atomic_write(string $directory, string $destination, string $content): bool
{
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $destination) || is_link($directory) || !is_dir($directory)) {
        return false;
    }

    try {
        $temporary = $directory . '/.tmp-' . bin2hex(random_bytes(16));
    } catch (Throwable $error) {
        return false;
    }
    $final = $directory . '/' . $destination;
    if (file_exists($final) || is_link($final)) {
        return false;
    }

    $handle = @fopen($temporary, 'x+b');
    if ($handle === false) {
        return false;
    }
    chmod($temporary, 0600);
    $written = 0;
    $length = strlen($content);
    while ($written < $length) {
        $result = fwrite($handle, substr($content, $written));
        if ($result === false || $result === 0) {
            fclose($handle);
            unlink($temporary);
            return false;
        }
        $written += $result;
    }
    $flushed = fflush($handle);
    if (function_exists('fsync')) {
        $flushed = fsync($handle) && $flushed;
    }
    fclose($handle);
    if (!$flushed || !rename($temporary, $final)) {
        @unlink($temporary);
        return false;
    }
    chmod($final, 0600);

    return !is_link($final) && (($permissions = fileperms($final)) !== false) && (($permissions & 0077) === 0);
}
