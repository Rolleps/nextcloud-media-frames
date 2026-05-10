<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Media Frames – Device Access</title>
  <link rel="stylesheet" href="<?= $_['appPath'] ?>/css/fonts.css" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #111;
      color: #eee;
      font-family: "Noto Sans", sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #222;
      border-radius: 12px;
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 360px;
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
      box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    }
    h1 { font-size: 1.3rem; font-weight: 600; }
    p { font-size: 0.95rem; color: #aaa; }
    input[type="password"] {
      width: 100%;
      padding: 0.7rem 1rem;
      border-radius: 8px;
      border: 1px solid #444;
      background: #333;
      color: #eee;
      font-size: 1rem;
    }
    input[type="password"]:focus { outline: none; border-color: #0082c9; }
    button {
      width: 100%;
      padding: 0.75rem;
      border-radius: 8px;
      border: none;
      background: #0082c9;
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
    }
    button:hover { background: #006aaa; }
    .error { color: #e74c3c; font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Device Access</h1>
    <p>This media frame is protected. Enter the device password to continue.</p>

    <?php if ($_['failed']): ?>
      <p class="error">Incorrect password. Please try again.</p>
    <?php endif ?>

    <form method="post" action="<?= htmlspecialchars($_['authUrl']) ?>">
      <input type="hidden" name="requesttoken" value="<?= \OCP\Util::callRegister() ?>" />
      <div style="display:flex;flex-direction:column;gap:0.8rem;">
        <input
          type="password"
          name="password"
          placeholder="Device password"
          autofocus
          autocomplete="current-password"
          required
        />
        <button type="submit">Unlock</button>
      </div>
    </form>
  </div>
</body>
</html>
