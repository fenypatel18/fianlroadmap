<?php
function fetch_single_value($conn, $sql) {
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return reset($row); // Return the first value in the row
    }
    return 0; // Return 0 if no result
}
?>