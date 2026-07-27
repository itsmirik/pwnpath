<?php

// admin.php
$stored = '0e462097431906509019562988736854';
if (isset($_GET['token']) && md5($_GET['token']) == $stored) {
    echo file_get_contents('/flag.txt'); // HTP{type_juggl1ng_php}
} else {
    http_response_code(403);
}
