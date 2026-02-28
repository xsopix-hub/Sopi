

<?php
// --- Konfiguration ---
$file = __DIR__ . "/gaestebuch.txt";
$maxLenName = 40;
$maxLenMsg  = 500;

// Datei anlegen, falls sie nicht existiert
if (!file_exists($file)) {
  file_put_contents($file, "");
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST["name"] ?? "");
  $msg  = trim($_POST["msg"] ?? "");

  if ($name === "" || $msg === "") {
    $error = "Bitte Name und Nachricht ausfüllen.";
  } elseif (mb_strlen($name) > $maxLenName) {
    $error = "Name ist zu lang (max. $maxLenName Zeichen).";
  } elseif (mb_strlen($msg) > $maxLenMsg) {
    $error = "Nachricht ist zu lang (max. $maxLenMsg Zeichen).";
  } else {
    // Zeilenumbrüche vereinheitlichen, Trennzeichen verhindern
    $name = str_replace(["\r", "\n", "|"], ["", "", "/"], $name);
    $msg  = str_replace(["\r", "|"], ["", "/"], $msg);

    $ts = time();
    // Format: timestamp|name|message
    $line = $ts . "|" . $name . "|" . str_replace("\n", "\\n", $msg) . "\n";

    // Mit Lock schreiben
    $fh = fopen($file, "a");
    if ($fh && flock($fh, LOCK_EX)) {
      fwrite($fh, $line);
      flock($fh, LOCK_UN);
      fclose($fh);
      // Redirect gegen Double-Submit
      header("Location: " . $_SERVER["PHP_SELF"]);
      exit;
    } else {
      $error = "Konnte nicht schreiben. Prüfe Dateirechte.";
    }
  }
}

// Einträge lesen (neueste zuerst)
$entries = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$entries = array_reverse($entries);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gästebuch</title>
  <style>
    body{font-family:system-ui,Arial,sans-serif;max-width:820px;margin:24px auto;padding:0 14px;}
    form{background:#f6f6f6;padding:14px;border-radius:10px;}
    input,textarea{width:100%;padding:10px;margin:6px 0 12px;border:1px solid #ccc;border-radius:8px;}
    button{padding:10px 14px;border:0;border-radius:8px;cursor:pointer;}
    .err{background:#ffe3e3;padding:10px;border-radius:8px;margin:10px 0;}
    .entry{border:1px solid #e6e6e6;border-radius:10px;padding:12px;margin:12px 0;}
    .meta{color:#666;font-size:0.9rem;margin-bottom:6px;}
  </style>
</head>
<body>

<h1>Gästebuch</h1>

<form method="post" action="">
  <?php if ($error): ?>
    <div class="err"><?= h($error) ?></div>
  <?php endif; ?>

  <label for="name">Name</label>
  <input id="name" name="name" maxlength="<?= $maxLenName ?>" required>

  <label for="msg">Nachricht</label>
  <textarea id="msg" name="msg" rows="5" maxlength="<?= $maxLenMsg ?>" required></textarea>

  <button type="submit">Eintragen</button>
</form>

<h2>Einträge</h2>

<?php if (count($entries) === 0): ?>
  <p>Noch keine Einträge.</p>
<?php endif; ?>

<?php foreach ($entries as $line): 
  $parts = explode("|", $line, 3);
  if (count($parts) !== 3) continue;
  [$ts, $name, $msg] = $parts;
  $msg = str_replace("\\n", "\n", $msg);
?>
  <div class="entry">
    <div class="meta">
      <strong><?= h($name) ?></strong> · <?= date("d.m.Y H:i", (int)$ts) ?>
    </div>
    <div><?= nl2br(h($msg)) ?></div>
  </div>
<?php endforeach; ?>

</body>
</html>
