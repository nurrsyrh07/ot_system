<?php
$password = "ckteh123"; // replace with the real password you want
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;