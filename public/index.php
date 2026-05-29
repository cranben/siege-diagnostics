<?php

// ======================================================
// NAVIGATION BAR
// ======================================================

include __DIR__ . '/../app/nav.php';

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Siege Diagnostics</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

<h1>Siege Diagnostics</h1>
<h2>CDR Import Lab</h2>

<form method="post" action="upload.php" enctype="multipart/form-data">

    <label>Select FusionPBX CDR CSV file:</label>

    <br><br>

    <input
        type="file"
        name="cdr_file"
        accept=".csv,text/csv"
        required
    >

    <br><br>

    <button type="submit">
        Upload CDR File
    </button>

</form>

</body>
</html>
