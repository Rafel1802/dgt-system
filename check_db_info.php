<?php
$conn = mysqli_connect('localhost', 'u768808434_dgt_system', 'KhmerLucky#2888', 'u768808434_dgt_system');
if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
} else {
    echo "Host info: " . mysqli_get_host_info($conn) . "\n";
    $result = mysqli_query($conn, "SHOW VARIABLES LIKE '%socket%'");
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Variable_name'] . " = " . $row['Value'] . "\n";
    }
    $result = mysqli_query($conn, "SHOW VARIABLES LIKE 'port'");
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Variable_name'] . " = " . $row['Value'] . "\n";
    }
    mysqli_close($conn);
}
