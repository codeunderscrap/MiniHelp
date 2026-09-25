<?php
// /_mmos/accept — MM OS SSO handoff page
// The browser lands here at /_mmos/accept#token=<jwt>. Inline JS extracts the
// token from the fragment, POSTs it to /_mmos/session, and redirects to the app.

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-store");
?>
<!doctype html>
<meta charset="utf-8">
<title>Signing in…</title>
<body style="font-family:system-ui,sans-serif;color:#48596A;background:#F0F4FA">
<p id="mmos-msg">Signing in…</p>
<script>
(function () {
  var frag = window.location.hash || "";
  var m = frag.match(/token=([^&]+)/);
  var msg = document.getElementById("mmos-msg");
  if (!m) { msg.textContent = "No token in the URL."; return; }
  var token = decodeURIComponent(m[1]);

  var params = new URLSearchParams(window.location.search);
  var next = params.get("next") || "/";
  if (!next.startsWith("/") || next.startsWith("//")) { next = "/"; }

  fetch("/_mmos/session", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify({ token: token })
  }).then(function (r) {
    if (!r.ok) throw new Error("rejected");
    return r.json();
  }).then(function (data) {
    history.replaceState(null, "", window.location.pathname);
    // Store user data and token for the SPA
    try {
      localStorage.setItem("minihelp_token", token);
      if (data.user) {
        localStorage.setItem("minihelp-auth-storage", JSON.stringify({
          state: { user: data.user, isAuthenticated: true },
          version: 0
        }));
      }
    } catch (_) {}
    window.location.replace(next);
  }).catch(function () {
    history.replaceState(null, "", window.location.pathname);
    msg.textContent = "Sign-in failed. Ask MM OS to send a new link.";
  });
})();
</script>
</body>
