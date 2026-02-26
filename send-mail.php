<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ======================
    // 1️⃣ reCAPTCHA
    // ======================
    $recaptchaSecret = "6LegDXoUAAAAAIEZRQDHecVfoNy4Dbcb9zMHf0xO"; // Twój Secret Key
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptchaResponse)) {
        echo "Błąd: potwierdź, że nie jesteś robotem.";
        exit;
    }

    $verify = file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}"
    );
    $captchaSuccess = json_decode($verify)->success;

    if (!$captchaSuccess) {
        echo "Błąd: weryfikacja reCAPTCHA nie powiodła się.";
        exit;
    }

    // ======================
    // 2️⃣ Dane formularza
    // ======================
    $to = "druk@plikdruk.pl"; // docelowy adres
    $subject = "Nowe zlecenie z formularza Plikdruk.pl";

    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $description = htmlspecialchars($_POST['description']);

    $messageBody = "Imie i nazwisko: $name\n";
    $messageBody .= "E-mail: $email\n";
    $messageBody .= "Telefon: $phone\n";
    $messageBody .= "Opis zlecenia:\n$description\n";

    $headers = "From: druk@plikdruk.pl";

    // ======================
    // 3️⃣ Obsługa załączników
    // ======================
    $files = $_FILES['files'] ?? null;
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    if ($files && count($files['name']) > 0) {
        $boundary = md5(time());
        $headers .= "\r\nMIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $messageBody . "\r\n";

        // Dodawanie plików
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                if ($files['size'][$i] > $maxFileSize) continue; // pomiń duże pliki

                $fileContent = chunk_split(base64_encode(file_get_contents($files['tmp_name'][$i])));
                $filename = basename($files['name'][$i]);
                $filetype = mime_content_type($files['tmp_name'][$i]);

                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: {$filetype}; name=\"{$filename}\"\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= $fileContent . "\r\n";
            }
        }
        $message .= "--{$boundary}--";

    } else {
        // Brak plików – zwykły mail
        $message = $messageBody;
    }

    // ======================
    // 4️⃣ Wysyłka maila
    // ======================
    if (mail($to, $subject, $message, $headers)) {
        echo "success"; // zwracamy do JS, aby pokazać zielony popup
    } else {
        echo "Błąd wysyłania wiadomości!";
    }

} else {
    echo "Nieprawidłowa metoda żądania.";
}
?>
