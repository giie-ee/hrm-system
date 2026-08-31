<?php

require_once "../config/database.php";

$sql = "SELECT
            employee_id,
            employee_number,
            first_name,
            last_name,
            employment_type,
            employment_status
        FROM employees";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Employees - HRMS</title>
</head>

<body>

    <h1>Employee List</h1>

    <?php if ($result && $result->num_rows > 0): ?>

        <table border="1" cellpadding="10">
            <tr>
                <th>ID</th>
                <th>Employee Number</th>
                <th>Name</th>
                <th>Employment Type</th>
                <th>Status</th>
            </tr>

            <?php while ($employee = $result->fetch_assoc()): ?>

                <tr>
                    <td><?php echo $employee['employee_id']; ?></td>

                    <td><?php echo $employee['employee_number']; ?></td>

                    <td>
                        <?php
                        echo $employee['first_name'] . " "
                           . $employee['last_name'];
                        ?>
                    </td>

                    <td><?php echo $employee['employment_type']; ?></td>

                    <td><?php echo $employee['employment_status']; ?></td>
                </tr>

            <?php endwhile; ?>

        </table>

    <?php else: ?>

        <p>No employees found.</p>

    <?php endif; ?>

</body>
</html>