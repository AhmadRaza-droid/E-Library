<?php

/** Opens the MySQL connection used by the library's existing mysqli helpers. */
final class Database
{
    public static function connect(string $host, string $user, string $password, string $database): mysqli|false
    {
        $connection = @mysqli_connect($host, $user, $password, $database);
        if (!$connection) {
            return false;
        }

        if (!mysqli_set_charset($connection, 'utf8mb4')) {
            error_log('Failed to set charset: ' . mysqli_error($connection));
        }

        return $connection;
    }
}
