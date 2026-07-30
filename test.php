<?php

$mysqli = new mysqli(
    "sql8.freesqldatabase.com",
    "sql8834207",
    "j3tnR9PmCb",
    "sql8834207"
);

if ($mysqli->connect_errno) {
    die($mysqli->connect_error);
}

echo "Connected successfully!";