<?php

function logError($conn, $message, $file = null, $line = null, $type = 'ERROR', $trace = null) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;

        $url = $_SERVER['REQUEST_URI'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO error_logs 
            (user_id, error_message, error_file, error_line, error_type, stack_trace, url, method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ississss",
            $user_id,
            $message,
            $file,
            $line,
            $type,
            $trace,
            $url,
            $method
        );

        $stmt->execute();
        $stmt->close();

    } catch (Throwable $e) {
        // fallback (optional)
        error_log("Error logging failed: " . $e->getMessage());
    }
}

set_error_handler(function($errno, $errstr, $errfile, $errline) use ($conn) {
    logError($conn, $errstr, $errfile, $errline, "PHP ERROR");
    return true; // prevent default PHP output
});

set_exception_handler(function($exception) use ($conn) {
    logError(
        $conn,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        "EXCEPTION",
        $exception->getTraceAsString()
    );
});

register_shutdown_function(function() use ($conn) {
    $error = error_get_last();
    if ($error !== null) {
        logError(
            $conn,
            $error['message'],
            $error['file'],
            $error['line'],
            "FATAL"
        );
    }
});