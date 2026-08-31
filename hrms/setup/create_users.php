<?php

require_once "../config/database.php";

$users = [
    [
        "employee_id" => 1,
        "role_id" => 1,
        "username" => "admin",
        "email" => "admin@hrms.local",
        "password" => "Admin@123"
    ],
    [
        "employee_id" => 2,
        "role_id" => 2,
        "username" => "hruser",
        "email" => "hr@hrms.local",
        "password" => "HR@123"
    ],
    [
        "employee_id" => 3,
        "role_id" => 3,
        "username" => "manager",
        "email" => "manager@hrms.local",
        "password" => "Manager@123"
    ],
    [
        "employee_id" => 4,
        "role_id" => 4,
        "username" => "employee",
        "email" => "employee@hrms.local",
        "password" => "Employee@123"
    ]
];

$sql = "INSERT INTO users
        (employee_id, role_id, username, email, password_hash, account_status)
        VALUES (?, ?, ?, ?, ?, 'Active')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

foreach ($users as $user) {

    $password_hash = password_hash(
        $user["password"],
        PASSWORD_DEFAULT
    );

    $stmt->bind_param(
        "iisss",
        $user["employee_id"],
        $user["role_id"],
        $user["username"],
        $user["email"],
        $password_hash
    );

    if ($stmt->execute()) {
        echo "Created user: " . $user["username"] . "<br>";
    } else {
        echo "Error creating "
            . $user["username"]
            . ": "
            . $stmt->error
            . "<br>";
    }
}

$stmt->close();
$conn->close();

echo "<br><strong>User setup completed.</strong>";

?>