<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ======================
    // 1️⃣ reCAPTCHA
    // ======================
    $recaptchaSecret = "6LegDXoUAAAAAIEZRQDHecVfoNy4Dbcb9zMHf0xO";
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptchaResponse)) {
        echo "Błąd: potwierdź, że nie jesteś robotem.";
        exit;
    }

    $verify = file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}"
    );

    if ($verify === false) {
        echo "Błąd: nie można zweryfikować reCAPTCHA. Spróbuj ponownie.";
        exit;
    }

    $captchaData = json_decode($verify);
    if (!$captchaData || !$captchaData->success) {
        echo "Błąd: weryfikacja reCAPTCHA nie powiodła się.";
        exit;
    }

    // ======================
    // 2️⃣ Dane formularza
    // ======================
    $name        = strip_tags(trim($_POST['name']        ?? ''));
    $phone       = strip_tags(trim($_POST['phone']       ?? ''));
    $description = strip_tags(trim($_POST['description'] ?? ''));

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        echo "Błąd: nieprawidłowy adres e-mail.";
        exit;
    }

    if (empty($name) || empty($email)) {
        echo "Błąd: uzupełnij wymagane pola.";
        exit;
    }

    $to      = "druk@plikdruk.pl";
    $subject = "Nowe zlecenie z formularza Plikdruk.pl";

    $messageBody  = "Imie i nazwisko: $name\r\n";
    $messageBody .= "E-mail: $email\r\n";
    $messageBody .= "Telefon: $phone\r\n";
    $messageBody .= "Opis zlecenia:\r\n$description\r\n";

    $headers  = "From: Formularz Plikdruk <druk@plikdruk.pl>\r\n";
    $headers .= "Reply-To: {$email}\r\n";

    // ======================
    // 3️⃣ Obsługa załączników
    // ======================
    $files = $_FILES['files'] ?? null;

    $maxFileSize = 5 * 1024 * 1024; // 5 MB na plik
    $maxFiles    = 5;

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'tif', 'tiff'];
    $allowedMimeTypes  = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if ($files && count(array_filter($files['name'])) > 0) {

        $fileCount = count(array_filter($files['name']));
        if ($fileCount > $maxFiles) {
            echo "Błąd: maksymalnie $maxFiles plików.";
            exit;
        }

        $boundary = md5(time());

        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $messageBody . "\r\n";

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > $maxFileSize)    continue;

            $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $mime = mime_content_type($files['tmp_name'][$i]);

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimeTypes)) {
                continue;
            }

            $fileContent = chunk_split(base64_encode(file_get_contents($files['tmp_name'][$i])));
            $filename    = basename($files['name'][$i]);

            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $fileContent . "\r\n";
        }

        $message .= "--{$boundary}--";

    } else {
        // Brak plików – zwykły mail
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8";
        $message  = $messageBody;
    }

    // ======================
    // 4️⃣ Wysyłka maila
    // ======================
    if (mail($to, $subject, $message, $headers)) {
        echo "success";
    } else {
        echo "Błąd wysyłania wiadomości!";
    }

} else {
    echo "Nieprawidłowa metoda żądania.";
}
?>