<?php
// Lightweight wrapper that opens the database view filtered to resolutions.
// Using a server-side redirect so URL is clean and the existing database view handles the filter.
header('Location: database.php?type=resolution');
exit;
?>